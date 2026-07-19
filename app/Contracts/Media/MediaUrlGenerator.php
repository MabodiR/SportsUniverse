<?php

namespace App\Contracts\Media;

use Illuminate\Database\Eloquent\Model;

interface MediaUrlGenerator
{
    public function url(Model $media, ?string $path = null): string;
}
