<?php

namespace App\Http\Controllers\Api\V1\Moderation;

use App\Domain\Feed\Models\Comment;
use App\Domain\Feed\Models\Video;
use App\Domain\Media\Models\Media;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\Message;
use App\Domain\Moderation\Models\ContentModerationAppeal;
use App\Domain\Moderation\Models\ModerationAction;
use App\Domain\Moderation\Models\Report;
use App\Domain\Moderation\Services\ModerationService;
use App\Domain\Opportunities\Models\Opportunity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Moderation\ModerateRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdminModerationController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $this->admin($request);

        return response()->json(['data' => ['users' => User::count(), 'active_users' => User::where('status', 'active')->count(), 'published_videos' => Video::where('status', 'published')->count(), 'pending_media' => Media::where('moderation_status', 'pending')->count(), 'open_reports' => Report::where('status', 'open')->count(), 'open_opportunities' => Opportunity::where('status', 'published')->where(fn ($q) => $q->whereNull('deadline')->orWhere('deadline', '>', now()))->count()]]);
    }

    public function queue(Request $request): JsonResponse
    {
        $this->admin($request);

        $reports = Report::with([
            'reporter:id,name', 'assignee:id,name',
            'reportable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    Video::class => ['user:id,name', 'media', 'images'],
                    Comment::class => ['user:id,name', 'video:id,public_id'],
                    Media::class => ['user:id,name'],
                    Message::class => ['sender:id,name', 'media'],
                    Conversation::class => ['participants:id,name', 'latestMessage.sender:id,name'],
                    User::class => ['profile:id,user_id,slug'],
                ]);
            },
        ])->whereIn('status', ['open', 'reviewing'])->latest()->limit(50)->get()
            ->map(fn (Report $report) => [...$report->toArray(), 'evidence' => $this->evidence($report->reportable)]);

        return response()->json(['data' => ['reports' => $reports, 'media' => Media::with('user:id,name')->whereIn('moderation_status', ['pending', 'flagged'])->latest()->limit(50)->get(), 'videos' => Video::with('user:id,name')->where('moderation_recommendation', 'review_for_removal')->latest('moderation_analyzed_at')->limit(100)->get(), 'appeals' => ContentModerationAppeal::with('video:id,public_id,caption,sports_relevance_score,moderation_reason,status', 'user:id,name')->whereIn('status', ['pending', 'reviewing'])->oldest()->limit(100)->get()]]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $this->admin($request);

        return response()->json(['data' => Video::with('user:id,name', 'media', 'images')->whereIn('moderation_recommendation', ['review_for_removal', 'removal_upheld'])->latest('moderation_analyzed_at')->paginate(50)]);
    }

    public function media(ModerateRequest $request, Media $media, ModerationService $service): JsonResponse
    {
        abort_unless(in_array($request->validated('status'), ['pending', 'approved', 'rejected', 'flagged'], true), 422, 'Invalid media status.');
        $service->media($request->user(), $media, $request->validated('status'), $request->validated('notes'));

        return response()->json(['message' => 'Media moderation updated.']);
    }

    public function video(ModerateRequest $request, Video $video, ModerationService $service): JsonResponse
    {
        abort_unless(in_array($request->validated('status'), ['published', 'hidden', 'flagged', 'rejected'], true), 422, 'Invalid video status.');
        $service->video($request->user(), $video, $request->validated('status'), $request->validated('notes'));

        return response()->json(['message' => 'Video moderation updated.']);
    }

    public function report(ModerateRequest $request, Report $report, ModerationService $service): JsonResponse
    {
        abort_unless(in_array($request->validated('status'), ['reviewing', 'resolved', 'dismissed'], true), 422, 'Invalid report status.');
        $service->resolve($request->user(), $report, $request->validated('status'), $request->validated('action', 'review_report'), $request->validated('notes'));

        return response()->json(['message' => 'Report updated.']);
    }

    public function verify(ModerateRequest $request, User $user, ModerationService $service): JsonResponse
    {
        $verified = $request->validated('status') === 'verified';
        abort_unless(in_array($request->validated('status'), ['verified', 'unverified'], true), 422, 'Invalid verification status.');
        $service->verify($request->user(), $user, $verified, $request->validated('notes'));

        return response()->json(['message' => 'Profile verification updated.']);
    }

    public function actions(Request $request): JsonResponse
    {
        $this->admin($request);

        return response()->json(['data' => ModerationAction::with('moderator:id,name')->latest()->paginate(50)]);
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'system_admin', 'super_admin']), 403);
    }

    private function evidence(mixed $target): array
    {
        return match (true) {
            $target instanceof Video => ['type' => 'Post', 'text' => $target->caption, 'author' => $target->user?->name, 'url' => '/feed#'.$target->public_id, 'media_url' => $target->media ? route('media.download', $target->media) : ($target->images->first() ? route('media.download', $target->images->first()) : null), 'mime_type' => $target->media?->mime_type ?? $target->images->first()?->mime_type],
            $target instanceof Comment => ['type' => 'Comment', 'text' => $target->body, 'author' => $target->user?->name, 'url' => $target->video ? '/feed#'.$target->video->public_id : null],
            $target instanceof Media => ['type' => 'Media', 'text' => $target->title ?: $target->original_name, 'author' => $target->user?->name, 'media_url' => route('media.download', $target), 'mime_type' => $target->mime_type],
            $target instanceof Message => ['type' => 'Message', 'text' => $target->body, 'author' => $target->sender?->name, 'media_url' => $target->media ? route('media.download', $target->media) : null, 'mime_type' => $target->media?->mime_type],
            $target instanceof Conversation => ['type' => 'Conversation', 'text' => $target->latestMessage?->body ?: 'Conversation reported for review.', 'author' => $target->participants->pluck('name')->join(' and ')],
            $target instanceof User => ['type' => 'Profile', 'text' => $target->name.' · '.$target->email, 'author' => $target->name, 'url' => $target->profile?->slug ? '/@'.$target->profile->slug : '/admin/users/'.$target->getKey()],
            default => ['type' => 'Unavailable content', 'text' => 'The reported content may have been deleted.'],
        };
    }
}
