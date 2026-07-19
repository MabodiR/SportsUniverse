<?php

namespace App\Domain\Media\Services;

use App\Domain\Media\Models\Media;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class MediaProcessor
{
    public function process(Media $media): array
    {
        $checksum = $this->checksum($media);
        $attributes = match ($media->kind) {
            'video' => $this->video($media),'image' => $this->image($media),default => []
        };

        return [...$attributes, 'checksum_sha256' => $checksum];
    }

    private function checksum(Media $media): string
    {
        $stream = Storage::disk($media->disk)->readStream($media->path);
        if (! is_resource($stream)) {
            throw new \RuntimeException('Media source is unavailable.');
        }

        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }

    private function video(Media $media): array
    {
        $source = $this->localCopy($media);
        $working = $source;
        $trimmed = null;
        $thumb = tempnam(sys_get_temp_dir(), 'su-thumb-').'.jpg';
        $renditionFiles = [];
        try {
            $startMs = max(0, (int) ($media->metadata['trim_start_ms'] ?? 0));
            $endMs = (int) ($media->metadata['trim_end_ms'] ?? 0);
            $clipDurationMs = $endMs > $startMs ? min(60000, $endMs - $startMs) : 60000;
            // Every published video is normalised through FFmpeg, even when a
            // client omits trim metadata. This guarantees a hard 60s maximum.
            $trimmed = tempnam(sys_get_temp_dir(), 'su-trim-').'.mp4';
            $filters = $this->filters($media, true);
            $quality = match ($media->metadata['quality'] ?? 'balanced') { 'high' => '19', 'space' => '27', default => '23' };
            $command = [config('media.ffmpeg_binary'), '-y', '-ss', number_format($startMs / 1000, 3, '.', ''), '-i', $source, '-t', number_format($clipDurationMs / 1000, 3, '.', '')];
            if ($filters !== '') array_push($command, '-vf', $filters);
            array_push($command, '-c:v', 'libx264', '-preset', 'medium', '-crf', $quality, '-c:a', 'aac', '-b:a', '128k', '-movflags', '+faststart', $trimmed);
            $trim = new Process($command);
            $trim->setTimeout(900);
            $trim->mustRun();
            $working = $trimmed;
            $stored = fopen($trimmed, 'rb');
            Storage::disk($media->disk)->put($media->path, $stored, ['visibility' => 'private']);
            fclose($stored);
            $probe = new Process([config('media.ffprobe_binary'), '-v', 'quiet', '-print_format', 'json', '-show_format', '-show_streams', $working]);
            $probe->setTimeout(120);
            $probe->mustRun();
            $data = json_decode($probe->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            $stream = collect($data['streams'] ?? [])->firstWhere('codec_type', 'video') ?? [];
            $ffmpeg = new Process([config('media.ffmpeg_binary'), '-y', '-ss', '00:00:01', '-i', $working, '-frames:v', '1', '-vf', 'scale=720:-2', $thumb]);
            $ffmpeg->setTimeout(180);
            $ffmpeg->mustRun();
            $thumbPath = "users/{$media->user_id}/thumbnails/{$media->public_id}.jpg";
            Storage::disk($media->disk)->put($thumbPath, file_get_contents($thumb), ['visibility' => 'private']);
            $renditions = [];
            foreach ([480, 720] as $width) {
                if (($stream['width'] ?? 0) <= $width) continue;
                $output = tempnam(sys_get_temp_dir(), "su-{$width}-").'.mp4';
                $renditionFiles[] = $output;
                $transcode = new Process([config('media.ffmpeg_binary'),'-y','-i',$working,'-vf',"scale={$width}:-2",'-c:v','libx264','-preset','veryfast','-crf','24','-c:a','aac','-b:a','128k','-movflags','+faststart',$output]);
                $transcode->setTimeout(600);
                $transcode->mustRun();
                $path = "users/{$media->user_id}/renditions/{$media->public_id}-{$width}p.mp4";
                Storage::disk($media->disk)->put($path, file_get_contents($output), ['visibility'=>'private']);
                $renditions["{$width}p"] = ['path'=>$path,'width'=>$width,'size_bytes'=>filesize($output)];
            }

            return ['thumbnail_path' => $thumbPath, 'duration_ms' => (int) round(((float) ($data['format']['duration'] ?? 0)) * 1000), 'width' => $stream['width'] ?? null, 'height' => $stream['height'] ?? null, 'size_bytes' => filesize($trimmed), 'metadata' => ['codec' => $stream['codec_name'] ?? null, 'trim_start_ms' => $startMs, 'trim_end_ms' => $startMs + $clipDurationMs, 'renditions'=>$renditions]];
        } finally {
            @unlink($source);
            if ($trimmed) @unlink($trimmed);
            @unlink($thumb);
            foreach ($renditionFiles as $file) @unlink($file);
        }
    }

    private function image(Media $media): array
    {
        $source = $this->localCopy($media);
        $optimized = tempnam(sys_get_temp_dir(), 'su-image-').'.jpg';
        try {
            $quality = match ($media->metadata['quality'] ?? 'balanced') { 'high' => '2', 'space' => '7', default => '4' };
            $filters = $this->filters($media, true);
            $command = [config('media.ffmpeg_binary'), '-y', '-i', $source];
            if ($filters !== '') array_push($command, '-vf', $filters);
            array_push($command, '-frames:v', '1', '-c:v', 'mjpeg', '-q:v', $quality, '-pix_fmt', 'yuvj420p', $optimized);
            $process = new Process($command);
            $process->setTimeout(300);
            $process->mustRun();
            $size = getimagesize($optimized);
            $path = "users/{$media->user_id}/image/{$media->public_id}.jpg";
            $stream = fopen($optimized, 'rb');
            Storage::disk($media->disk)->put($path, $stream, ['visibility' => 'private']);
            fclose($stream);
            if ($path !== $media->path) Storage::disk($media->disk)->delete($media->path);

            return ['path' => $path, 'mime_type' => 'image/jpeg', 'size_bytes' => filesize($optimized), 'width' => $size[0] ?? null, 'height' => $size[1] ?? null, 'metadata' => [...($media->metadata ?? []), 'optimized' => true, 'format' => 'jpeg']];
        } finally {
            @unlink($source);
            @unlink($optimized);
        }
    }

    private function filters(Media $media, bool $resize): string
    {
        $metadata = $media->metadata ?? [];
        $filters = [];
        $rotation = (int) ($metadata['rotation'] ?? 0);
        if ($rotation === 90) $filters[] = 'transpose=1';
        if ($rotation === 180) $filters[] = 'hflip,vflip';
        if ($rotation === 270) $filters[] = 'transpose=2';
        $brightness = max(-100, min(100, (int) ($metadata['brightness'] ?? 0))) / 100;
        $contrast = 1 + max(-100, min(100, (int) ($metadata['contrast'] ?? 0))) / 100;
        $saturation = 1 + max(-100, min(100, (int) ($metadata['saturation'] ?? 0))) / 100;
        if ($brightness !== 0.0 || $contrast !== 1.0 || $saturation !== 1.0) {
            $filters[] = sprintf('eq=brightness=%.2f:contrast=%.2f:saturation=%.2f', $brightness, max(.1, $contrast), max(0, $saturation));
        }
        if ($resize) {
            $width = (int) ($metadata['output_width'] ?? 1080);
            $filters[] = "scale='min({$width},iw)':-2";
        }

        return implode(',', $filters);
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
