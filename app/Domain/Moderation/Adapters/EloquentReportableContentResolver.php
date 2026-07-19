<?php

namespace App\Domain\Moderation\Adapters;

use App\Contracts\Moderation\ReportableContentResolver;
use App\Domain\Feed\Models\Comment;
use App\Domain\Feed\Models\Video;
use App\Domain\Media\Models\Media;
use App\Domain\Messaging\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class EloquentReportableContentResolver implements ReportableContentResolver
{
    public function resolve(string $type, string $publicId): Model
    {
        return match ($type) {
            'video' => Video::where('public_id', $publicId)->firstOrFail(),
            'comment' => Comment::where('public_id', $publicId)->firstOrFail(),
            'media' => Media::where('public_id', $publicId)->firstOrFail(),
            'message' => Message::where('public_id', $publicId)->firstOrFail(),
            'user' => User::findOrFail((int) $publicId),
            default => throw new InvalidArgumentException("Unsupported reportable content type [{$type}]."),
        };
    }
}
