<?php

namespace App\Http\Controllers\Web;

use App\Domain\Feed\Models\Video;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class VideoStreamController extends Controller
{
    public function __invoke(Video $video): Response
    {
        abort_unless($video->status === 'published' && $video->visibility === 'public', 404);
        $media = $video->media;
        $quality = request()->string('quality')->value();
        $renditions = $media?->metadata['renditions'] ?? [];
        $path = $quality ? ($renditions[$quality]['path'] ?? null) : ($renditions['720p']['path'] ?? $renditions['480p']['path'] ?? $media?->path);
        abort_unless($media && $path && Storage::disk($media->disk)->exists($path), 404);

        $headers = [
            'Content-Type' => 'video/mp4',
            'Cache-Control' => 'public, max-age=3600',
            'Content-Disposition' => 'inline',
            'Accept-Ranges' => 'bytes',
        ];

        if (config("filesystems.disks.{$media->disk}.driver") === 'local') {
            return response()->file(Storage::disk($media->disk)->path($path), $headers);
        }

        return Storage::disk($media->disk)->response($path, $media->original_name, $headers);
    }
}
