<?php

namespace App\Domain\Feed\Jobs;

use App\Domain\Feed\Models\Video;
use App\Domain\Feed\Services\AutomatedContentAnalyzer;
use App\Domain\Feed\Services\ContentFeatureExtractor;
use App\Events\NotificationRequested;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class AnalyzeVideoContent implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $videoId)
    {
        $this->onQueue('media');
    }

    public function handle(ContentFeatureExtractor $extractor, AutomatedContentAnalyzer $automated): void
    {
        $video = Video::with('sport', 'user.athleteProfile.sport', 'media', 'images')->find($this->videoId);
        if (! $video) {
            return;
        }
        $analysis = $automated->analyze($video);
        if ($analysis) {
            $video->update(['transcript' => $analysis['transcript'] ?? $video->transcript, 'detected_text' => $analysis['detected_text'] ?? $video->detected_text]);
        }
        $features = $extractor->extract($video->fresh(['sport', 'user.athleteProfile.sport']));
        if (! empty($analysis['embedding'])) {
            $features['embedding'] = $analysis['embedding'];
        }
        DB::transaction(function () use ($video, $features, $analysis) {
            $score = isset($analysis['sports_relevance_score']) ? max(0, min(1, (float) $analysis['sports_relevance_score'])) : null;
            $review = $score !== null && $score < config('recommendations.sports_review_threshold');
            $reason = $analysis['moderation_reason'] ?? ($review ? 'The visual content does not appear to be related to sport.' : null);
            $wasPublished = $video->status === 'published';
            $video->update([
                'content_labels' => $features['labels'], 'content_embedding' => $features['embedding'], 'analyzed_at' => now(),
                'sports_relevance_score' => $score,
                'moderation_recommendation' => $score === null ? null : ($review ? 'review_for_removal' : 'keep'),
                'moderation_reason' => $reason,
                'moderation_analyzed_at' => $score === null ? null : now(),
                'status' => $review && $video->status === 'published' ? 'flagged' : $video->status,
            ]);
            if ($review && $wasPublished) {
                NotificationRequested::dispatch($video->user_id, 'moderation', [
                    'event' => 'sports_content_review_suggested', 'video_id' => $video->public_id,
                    'score' => $score, 'reason' => $reason, 'can_appeal' => true,
                    'preview' => 'Our automated review could not confirm that this post is sports-related. It was sent to moderation, and you can request another review.',
                ]);
            }
            DB::table('video_content_topics')->where('video_id', $video->id)->delete();
            $now = now();
            DB::table('video_content_topics')->insert($features['topics']->map(fn ($topic) => [...$topic, 'video_id' => $video->id, 'created_at' => $now, 'updated_at' => $now])->all());
        });
    }
}
