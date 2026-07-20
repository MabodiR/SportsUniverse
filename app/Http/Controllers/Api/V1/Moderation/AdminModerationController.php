<?php

namespace App\Http\Controllers\Api\V1\Moderation;

use App\Domain\Feed\Models\Video;
use App\Domain\Media\Models\Media;
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

        return response()->json(['data' => ['reports' => Report::with('reporter:id,name', 'assignee:id,name')->whereIn('status', ['open', 'reviewing'])->latest()->limit(50)->get(), 'media' => Media::with('user:id,name')->whereIn('moderation_status', ['pending', 'flagged'])->latest()->limit(50)->get(), 'videos' => Video::with('user:id,name')->where('moderation_recommendation', 'review_for_removal')->latest('moderation_analyzed_at')->limit(100)->get(), 'appeals' => ContentModerationAppeal::with('video:id,public_id,caption,sports_relevance_score,moderation_reason,status', 'user:id,name')->whereIn('status', ['pending', 'reviewing'])->oldest()->limit(100)->get()]]);
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
        abort_unless($request->user()?->hasRole('admin'), 403);
    }
}
