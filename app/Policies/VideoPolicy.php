<?php

namespace App\Policies;

use App\Domain\Feed\Models\Video;
use App\Models\User;

class VideoPolicy
{
    public function view(?User $user, Video $video): bool
    {
        return ($video->status === 'published' && $video->visibility === 'public') || $user?->id === $video->user_id || $user?->hasRole('admin');
    }

    public function update(User $user, Video $video): bool
    {
        return $user->id === $video->user_id || $user->hasRole('admin');
    }

    public function delete(User $user, Video $video): bool
    {
        return $this->update($user, $video);
    }
}
