<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ConvertDummyPostsToVideos extends Command
{
    protected $signature = 'feed:convert-dummy-posts-to-videos {--dry-run}';

    protected $description = 'Ensure seeded and mass-feed dummy posts use playable video media';

    public function handle(): int
    {
        $sourceIds = DB::table('media')
            ->where('kind', 'video')
            ->where('processing_status', 'ready')
            ->where('moderation_status', 'approved')
            ->where('collection', '!=', 'performance-scale')
            ->where('original_name', 'not like', 'dummy-highlight-%')
            ->where('path', 'not like', 'demo/videos/%')
            ->orderByRaw("CASE WHEN collection = 'performance-sports' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get(['id', 'disk', 'path'])
            ->filter(fn ($media) => Storage::disk($media->disk)->exists($media->path))
            ->pluck('id')
            ->values();

        if ($sourceIds->isEmpty()) {
            throw new RuntimeException('No playable, ready and approved source videos are available.');
        }

        $target = $this->dummyPosts();
        $total = (clone $target)->count();
        $imageAttachments = DB::table('video_images')->whereIn('video_id', (clone $target)->select('videos.id'))->count();

        if ($this->option('dry-run')) {
            $this->info("Would convert {$total} dummy posts using {$sourceIds->count()} playable source videos and remove {$imageAttachments} image attachments.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($target, $sourceIds) {
            DB::table('videos')
                ->whereIn('id', (clone $target)->select('videos.id'))
                ->update(['media_id' => $sourceIds->first(), 'updated_at' => now()]);
            DB::table('video_images')->whereIn('video_id', (clone $this->dummyPosts())->select('videos.id'))->delete();
        });

        $remaining = DB::table('video_images')->whereIn('video_id', (clone $this->dummyPosts())->select('videos.id'))->count();
        $this->info("Converted {$total} dummy posts to playable videos; {$remaining} image attachments remain.");

        return $remaining === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function dummyPosts(): Builder
    {
        return DB::table('videos')
            ->join('users', 'users.id', '=', 'videos.user_id')
            ->where(function (Builder $query) {
                $query->where('users.email', 'like', 'dummy.sa.%@sportuniverse.test')
                    ->orWhere('users.email', 'like', 'athlete%@sportuniverse.test')
                    ->orWhere('videos.public_id', 'like', '5M%');
            });
    }
}
