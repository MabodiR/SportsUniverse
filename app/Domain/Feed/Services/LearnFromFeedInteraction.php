<?php

namespace App\Domain\Feed\Services;

use App\Domain\Feed\Jobs\AnalyzeVideoContent;
use App\Domain\Feed\Models\Video;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LearnFromFeedInteraction
{
    private const WEIGHTS = ['impression' => .02, 'view' => .25, 'complete' => 1.2, 'replay' => 1.6, 'like' => 2.0, 'comment' => 2.5, 'save' => 3.0, 'share' => 3.5, 'skip' => -.8, 'not_interested' => -5.0];

    public function record(User $user, Video $video, string $event, array $metadata = []): void
    {
        $weight = self::WEIGHTS[$event] ?? 0.0;
        if ($weight === 0.0 || $video->user_id === $user->id) return;
        if (! DB::table('video_content_topics')->where('video_id', $video->id)->exists()) {
            AnalyzeVideoContent::dispatchSync($video->id);
        }
        DB::transaction(function () use ($user, $video, $event, $metadata, $weight) {
            DB::table('feed_interactions')->insert(['user_id' => $user->id, 'video_id' => $video->id, 'event' => $event, 'weight' => $weight, 'metadata' => $metadata ? json_encode($metadata) : null, 'created_at' => now(), 'updated_at' => now()]);
            foreach (DB::table('video_content_topics')->where('video_id', $video->id)->get() as $topic) {
                $delta = $weight * (float) $topic->weight;
                $existing = DB::table('user_content_preferences')->where(['user_id' => $user->id, 'dimension' => $topic->dimension, 'value' => $topic->value])->lockForUpdate()->first();
                if ($existing) {
                    DB::table('user_content_preferences')->where('id', $existing->id)->update(['score' => max(-50, min(100, (float) $existing->score * .995 + $delta)), 'signals_count' => $existing->signals_count + 1, 'last_signaled_at' => now(), 'updated_at' => now()]);
                } else {
                    DB::table('user_content_preferences')->insert(['user_id' => $user->id, 'dimension' => $topic->dimension, 'value' => $topic->value, 'score' => $delta, 'signals_count' => 1, 'last_signaled_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            DB::table('recommendation_feed_items')->where('user_id', $user->id)->delete();
        });
        app(RecommendationFeed::class)->invalidate($user->id);
    }
}
