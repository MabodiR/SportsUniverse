<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ConvertDummyPostsToVideos extends Command
{
    protected $signature = 'feed:convert-dummy-posts-to-videos {--dry-run}';

    protected $description = 'Ensure seeded and mass-feed dummy posts use playable video media';

    public function handle(): int
    {
        $sources = DB::table('media')
            ->where('kind', 'video')
            ->where('processing_status', 'ready')
            ->where('moderation_status', 'approved')
            ->where('collection', '!=', 'performance-scale')
            ->where('original_name', 'not like', 'dummy-highlight-%')
            ->where('path', 'not like', 'demo/videos/%')
            ->orderByRaw("CASE WHEN collection = 'performance-sports' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->limit(100)
            ->get([
                'id', 'disk', 'path', 'title', 'description', 'original_name', 'mime_type', 'size_bytes',
                'checksum_sha256', 'thumbnail_path', 'duration_ms', 'width', 'height', 'metadata',
            ])
            ->filter(fn ($media) => Storage::disk($media->disk)->exists($media->path))
            ->values();

        if ($sources->isEmpty()) {
            throw new RuntimeException('No playable, ready and approved source videos are available.');
        }

        $target = $this->dummyPosts();
        $total = (clone $target)->count();
        $imageAttachments = DB::table('video_images')->whereIn('video_id', (clone $target)->select('videos.id'))->count();
        $replacementPosts = $this->dummyPosts()
            ->leftJoin('media', 'media.id', '=', 'videos.media_id')
            ->where(function (Builder $query) {
                $query->whereNull('media.id')
                    ->orWhere('media.kind', '!=', 'video')
                    ->orWhere('media.processing_status', '!=', 'ready')
                    ->orWhere('media.moderation_status', '!=', 'approved')
                    ->orWhere('media.path', 'like', 'demo/videos/%');
            })
            ->select('videos.id', 'videos.user_id')
            ->get();

        if ($this->option('dry-run')) {
            $this->info("Would verify {$total} dummy posts, replace media for {$replacementPosts->count()} posts using {$sources->count()} playable source videos, and remove {$imageAttachments} image attachments.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($replacementPosts, $sources) {
            foreach ($replacementPosts as $index => $post) {
                $source = $sources[$index % $sources->count()];
                $mediaId = DB::table('media')->insertGetId([
                    'public_id' => (string) Str::ulid(),
                    'user_id' => $post->user_id,
                    'kind' => 'video',
                    'collection' => 'demo-videos',
                    'title' => $source->title,
                    'description' => $source->description,
                    'disk' => $source->disk,
                    'path' => $source->path,
                    'original_name' => 'demo-video-'.$post->id.'-'.$source->original_name,
                    'mime_type' => $source->mime_type,
                    'size_bytes' => $source->size_bytes,
                    'checksum_sha256' => $source->checksum_sha256,
                    'processing_status' => 'ready',
                    'moderation_status' => 'approved',
                    'thumbnail_path' => $source->thumbnail_path,
                    'duration_ms' => $source->duration_ms,
                    'width' => $source->width,
                    'height' => $source->height,
                    'metadata' => $source->metadata,
                    'processing_error' => null,
                    'processed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('videos')->where('id', $post->id)->update(['media_id' => $mediaId, 'updated_at' => now()]);
            }
            DB::table('video_images')->whereIn('video_id', (clone $this->dummyPosts())->select('videos.id'))->delete();
        });

        $remaining = DB::table('video_images')->whereIn('video_id', (clone $this->dummyPosts())->select('videos.id'))->count();
        $this->info("Verified {$total} dummy posts, replaced {$replacementPosts->count()} invalid media records, and left {$remaining} image attachments.");

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
