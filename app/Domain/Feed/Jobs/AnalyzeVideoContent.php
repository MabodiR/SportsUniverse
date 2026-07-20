<?php

namespace App\Domain\Feed\Jobs;

use App\Domain\Feed\Models\Video;
use App\Domain\Feed\Services\ContentFeatureExtractor;
use App\Domain\Feed\Services\AutomatedContentAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class AnalyzeVideoContent implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $videoId) { $this->onQueue('media'); }

    public function handle(ContentFeatureExtractor $extractor, AutomatedContentAnalyzer $automated): void
    {
        $video = Video::with('sport', 'user.athleteProfile.sport')->find($this->videoId);
        if (! $video) return;
        $analysis = $automated->analyze($video);
        if ($analysis) {
            $video->update(['transcript' => $analysis['transcript'] ?? $video->transcript, 'detected_text' => $analysis['detected_text'] ?? $video->detected_text]);
        }
        $features = $extractor->extract($video->fresh(['sport', 'user.athleteProfile.sport']));
        if (! empty($analysis['embedding'])) $features['embedding'] = $analysis['embedding'];
        DB::transaction(function () use ($video, $features) {
            $video->update(['content_labels' => $features['labels'], 'content_embedding' => $features['embedding'], 'analyzed_at' => now()]);
            DB::table('video_content_topics')->where('video_id', $video->id)->delete();
            $now = now();
            DB::table('video_content_topics')->insert($features['topics']->map(fn ($topic) => [...$topic, 'video_id' => $video->id, 'created_at' => $now, 'updated_at' => $now])->all());
        });
    }
}
