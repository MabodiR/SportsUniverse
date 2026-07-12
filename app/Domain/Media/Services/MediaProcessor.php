<?php

namespace App\Domain\Media\Services;

use App\Domain\Media\Models\Media;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class MediaProcessor
{
    public function process(Media $media): array
    {
        return match ($media->kind) {
            'video' => $this->video($media),'image' => $this->image($media),default => []
        };
    }

    private function video(Media $media): array
    {
        $source = $this->localCopy($media);
        $thumb = tempnam(sys_get_temp_dir(), 'su-thumb-').'.jpg';
        try {
            $probe = new Process([config('media.ffprobe_binary'), '-v', 'quiet', '-print_format', 'json', '-show_format', '-show_streams', $source]);
            $probe->setTimeout(120);
            $probe->mustRun();
            $data = json_decode($probe->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            $stream = collect($data['streams'] ?? [])->firstWhere('codec_type', 'video') ?? [];
            $ffmpeg = new Process([config('media.ffmpeg_binary'), '-y', '-ss', '00:00:01', '-i', $source, '-frames:v', '1', '-vf', 'scale=720:-2', $thumb]);
            $ffmpeg->setTimeout(180);
            $ffmpeg->mustRun();
            $thumbPath = "users/{$media->user_id}/thumbnails/{$media->public_id}.jpg";
            Storage::disk($media->disk)->put($thumbPath, file_get_contents($thumb), ['visibility' => 'private']);

            return ['thumbnail_path' => $thumbPath, 'duration_ms' => (int) round(((float) ($data['format']['duration'] ?? 0)) * 1000), 'width' => $stream['width'] ?? null, 'height' => $stream['height'] ?? null, 'metadata' => ['codec' => $stream['codec_name'] ?? null]];
        } finally {
            @unlink($source);
            @unlink($thumb);
        }
    }

    private function image(Media $media): array
    {
        $source = $this->localCopy($media);
        try {
            $size = getimagesize($source);

            return ['width' => $size[0] ?? null, 'height' => $size[1] ?? null];
        } finally {
            @unlink($source);
        }
    }

    private function localCopy(Media $media): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'su-media-');
        $stream = Storage::disk($media->disk)->readStream($media->path);
        if (! is_resource($stream)) {
            throw new \RuntimeException('Media source is unavailable.');
        }$target = fopen($temp, 'wb');
        stream_copy_to_stream($stream, $target);
        fclose($stream);
        fclose($target);

        return $temp;
    }
}
