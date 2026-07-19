<?php

namespace App\Domain\Media\Services;

use App\Contracts\Media\MediaLibrary;
use App\Domain\Media\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EloquentMediaLibrary implements MediaLibrary
{
    public function findOwned(int $userId, string $publicId): ?Model
    {
        return Media::query()->where('public_id', $publicId)->where('user_id', $userId)->first();
    }

    public function findOwnedReadyApproved(int $userId, string $publicId): ?Model
    {
        return Media::query()->where('public_id', $publicId)->where('user_id', $userId)
            ->where('processing_status', 'ready')->where('moderation_status', 'approved')->first();
    }

    public function findOwnedMany(int $userId, array $publicIds): Collection
    {
        return Media::query()->whereIn('public_id', $publicIds)->where('user_id', $userId)->get();
    }
}
