<?php

namespace App\Contracts\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface MediaLibrary
{
    public function findOwned(int $userId, string $publicId): ?Model;
    public function findOwnedReadyApproved(int $userId, string $publicId): ?Model;
    public function findOwnedMany(int $userId, array $publicIds): Collection;
}
