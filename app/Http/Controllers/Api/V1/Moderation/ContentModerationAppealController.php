<?php

namespace App\Http\Controllers\Api\V1\Moderation;

use App\Domain\Feed\Models\Video;
use App\Domain\Moderation\Models\ContentModerationAppeal;
use App\Domain\Moderation\Models\ModerationAction;
use App\Events\NotificationRequested;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContentModerationAppealController extends Controller
{
    public function store(Request $request, Video $video): JsonResponse
    {
        abort_unless($video->user_id === $request->user()->id, 403);
        abort_unless($video->moderation_recommendation === 'review_for_removal' && in_array($video->status, ['flagged', 'rejected', 'hidden'], true), 422, 'This post is not eligible for another review.');
        abort_if($video->moderationAppeals()->whereIn('status', ['pending', 'reviewing'])->exists(), 409, 'A review request is already pending.');
        $data = $request->validate(['message' => ['required', 'string', 'min:10', 'max:2000']]);
        $appeal = $video->moderationAppeals()->create(['public_id' => (string) Str::ulid(), 'user_id' => $request->user()->id, 'message' => $data['message'], 'status' => 'pending']);

        return response()->json(['message' => 'Your message was sent to the moderation team for another review.', 'data' => ['id' => $appeal->public_id, 'status' => $appeal->status]], 201);
    }

    public function resolve(Request $request, ContentModerationAppeal $appeal): JsonResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        $data = $request->validate(['decision' => ['required', Rule::in(['restore', 'remove'])], 'notes' => ['nullable', 'string', 'max:2000']]);
        $restored = $data['decision'] === 'restore';
        $previous = $appeal->video->status;
        $appeal->video->update(['status' => $restored ? 'published' : 'rejected', 'moderation_recommendation' => $restored ? 'keep_after_appeal' : 'removal_upheld']);
        $appeal->update(['status' => $restored ? 'approved' : 'denied', 'resolution_notes' => $data['notes'] ?? null, 'reviewed_by_id' => $request->user()->id, 'reviewed_at' => now()]);
        ModerationAction::create(['moderator_id' => $request->user()->id, 'moderatable_type' => $appeal->video->getMorphClass(), 'moderatable_id' => $appeal->video_id, 'action' => 'resolve_ai_content_appeal', 'previous_status' => $previous, 'new_status' => $appeal->video->status, 'notes' => $data['notes'] ?? null, 'metadata' => ['appeal_id' => $appeal->public_id, 'decision' => $data['decision']]]);
        NotificationRequested::dispatch($appeal->user_id, 'moderation', ['event' => 'sports_content_appeal_resolved', 'video_id' => $appeal->video->public_id, 'decision' => $data['decision'], 'notes' => $data['notes'] ?? null, 'preview' => $restored ? 'Your post was restored after another review.' : 'The removal decision was upheld after another review.']);

        return response()->json(['message' => $restored ? 'Post restored.' : 'Removal upheld.']);
    }
}
