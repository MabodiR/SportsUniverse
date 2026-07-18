<?php

namespace App\Domain\Feed\Jobs;

use App\Domain\Feed\Models\Video;
use App\Domain\Notifications\Services\NotificationDispatcher;
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

    public function handle(NotificationDispatcher $notifications): void
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
            $this->notifyFailure($notifications, $video, 'We could not process your media. Open your drafts to retry the upload.');
            return;
        }

        if ($media->contains(fn ($item) => in_array($item->processing_status, ['pending', 'processing'], true))) {
            $this->release(10);
            return;
        }

        if ($media->contains(fn ($item) => $item->moderation_status === 'rejected')) {
            $this->notifyFailure($notifications, $video, 'Your upload could not be published because its media was not approved.');
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
        $notifications->send($video->user, 'moderation', [
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
            $this->notifyFailure(app(NotificationDispatcher::class), $video, 'Your upload is taking longer than expected. Open your drafts to check it.');
        }
    }

    private function notifyFailure(NotificationDispatcher $notifications, Video $video, string $message): void
    {
        $notifications->send($video->user, 'moderation', [
            'event' => 'post_upload_failed',
            'video_id' => $video->public_id,
            'message' => $message,
            'preview' => $message,
            'status' => 'failed',
        ]);
    }
}
