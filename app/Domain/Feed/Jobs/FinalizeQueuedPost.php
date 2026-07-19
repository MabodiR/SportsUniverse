<?php

namespace App\Domain\Feed\Jobs;

use App\Domain\Feed\Models\Video;
use App\Events\NotificationRequested;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class FinalizeQueuedPost implements ShouldQueue
{
    use Queueable;

    public int $tries = 240;

    public int $timeout = 60;

    public function __construct(public Video $video, public bool $publishWhenReady)
    {
        $this->onQueue('media');
    }

    public function handle(): void
    {
        $video = $this->video->fresh(['user', 'media', 'images']);
        if (! $video) {
            return;
        }

        $media = collect([$video->media])->filter()->concat($video->images);
        if ($media->isEmpty()) {
            return;
        }

        if ($media->contains(fn ($item) => $item->processing_status === 'failed')) {
            $this->notifyFailure($video, 'We could not process your media. Open your drafts to retry the upload.');
            return;
        }

        if ($media->contains(fn ($item) => in_array($item->processing_status, ['pending', 'processing'], true))) {
            $this->release(10);
            return;
        }

        if ($media->contains(fn ($item) => $item->moderation_status === 'rejected')) {
            $this->notifyFailure($video, 'Your upload could not be published because its media was not approved.');
            return;
        }

        if ($media->contains(fn ($item) => $item->moderation_status !== 'approved')) {
            $this->release(30);
            return;
        }

        if ($this->publishWhenReady && $video->status === 'draft') {
            $video->update(['status' => 'published', 'published_at' => now()]);
        }

        $published = $video->fresh()->status === 'published';
        NotificationRequested::dispatch($video->user_id, 'moderation', [
            'event' => 'post_upload_completed',
            'video_id' => $video->public_id,
            'message' => $published ? 'Your post uploaded successfully and is now live.' : 'Your media uploaded successfully and your draft is ready.',
            'preview' => $published ? 'Your post uploaded successfully and is now live.' : 'Your media uploaded successfully and your draft is ready.',
            'status' => $published ? 'published' : 'draft',
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $video = $this->video->fresh(['user']);
        if ($video) {
            $this->notifyFailure($video, 'Your upload is taking longer than expected. Open your drafts to check it.');
        }
    }

    private function notifyFailure(Video $video, string $message): void
    {
        NotificationRequested::dispatch($video->user_id, 'moderation', [
            'event' => 'post_upload_failed',
            'video_id' => $video->public_id,
            'message' => $message,
            'preview' => $message,
            'status' => 'failed',
        ]);
    }
}
