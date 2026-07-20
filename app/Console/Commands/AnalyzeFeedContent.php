<?php

namespace App\Console\Commands;

use App\Domain\Feed\Jobs\AnalyzeVideoContent;
use App\Domain\Feed\Models\Video;
use Illuminate\Console\Command;

class AnalyzeFeedContent extends Command
{
    protected $signature = 'feed:analyze-content {--sync : Analyze inside this process} {--force : Re-analyze posts that already have features}';
    protected $description = 'Extract recommendation topics and embeddings from feed posts';

    public function handle(): int
    {
        $query = Video::query()->when(! $this->option('force'), fn ($videos) => $videos->whereNull('analyzed_at'));
        $total = (clone $query)->count();
        $query->select('id')->chunkById(200, function ($videos) {
            foreach ($videos as $video) {
                $this->option('sync') ? AnalyzeVideoContent::dispatchSync($video->id) : AnalyzeVideoContent::dispatch($video->id);
            }
        });
        $this->info($this->option('sync') ? "Analyzed {$total} posts." : "Queued {$total} posts for analysis.");

        return self::SUCCESS;
    }
}
