<?php

namespace App\Domain\Media\Services;

use App\Domain\Media\Models\Media;

class MediaDelivery
{
    public static function url(Media $media, ?string $path = null): string
    {
        $path ??= $media->path;
        if ($cdn = config('scale.cdn_url')) return $cdn.'/'.ltrim($path, '/');
        return route('media.download', $media);
    }
}
