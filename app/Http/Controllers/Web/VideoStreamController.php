<?php

namespace App\Http\Controllers\Web;

use App\Domain\Feed\Models\Video;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoStreamController extends Controller
{
    public function __invoke(Video $video): StreamedResponse
    {
        abort_unless($video->status === 'published' && $video->visibility === 'public', 404);
        $media = $video->media;
        abort_unless($media && Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->response($media->path, $media->original_name, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'public, max-age=3600',
            'Content-Disposition' => 'inline',
        ]);
    }
}
