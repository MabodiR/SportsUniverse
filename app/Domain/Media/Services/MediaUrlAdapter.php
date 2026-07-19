<?php

namespace App\Domain\Media\Services;

use App\Contracts\Media\MediaUrlGenerator;
use Illuminate\Database\Eloquent\Model;

class MediaUrlAdapter implements MediaUrlGenerator
{
    public function url(Model $media, ?string $path = null): string
    {
        return MediaDelivery::url($media, $path);
    }
}
