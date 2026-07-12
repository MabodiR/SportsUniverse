<?php

namespace App\Policies;

use App\Domain\Media\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function view(?User $user, Media $media): bool
    {
        return $media->moderation_status === 'approved' || $user?->id === $media->user_id || $user?->hasRole('admin');
    }

    public function delete(User $user, Media $media): bool
    {
        return $user->id === $media->user_id || $user->hasRole('admin');
    }
}
