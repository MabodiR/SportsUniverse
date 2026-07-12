<?php

namespace App\Domain\Media\Jobs;

use App\Domain\Media\Models\Media;
use App\Domain\Media\Services\MediaProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1800;

    public function __construct(public Media $media)
    {
        $this->onQueue('media');
    }

    public function handle(MediaProcessor $processor): void
    {
        $this->media->update(['processing_status' => 'processing', 'processing_error' => null]);
        $attributes = $processor->process($this->media);
        $this->media->update([...$attributes, 'processing_status' => 'ready', 'processed_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->media->update(['processing_status' => 'failed', 'processing_error' => str($exception?->getMessage())->limit(2000)]);
    }
}
