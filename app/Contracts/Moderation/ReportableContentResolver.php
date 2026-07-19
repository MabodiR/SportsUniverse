<?php

namespace App\Contracts\Moderation;

use Illuminate\Database\Eloquent\Model;

interface ReportableContentResolver
{
    public function resolve(string $type, string $publicId): Model;
}
