<?php

namespace App\Policies;

use App\Domain\Feed\Models\Video;
use App\Models\User;

class VideoPolicy
{
    public function view(?User $user, Video $video): bool
    {
        if ($user?->id === $video->user_id || $user?->hasRole('admin')) return true;
        if ($video->status !== 'published' || ($video->post_type === 'story' && $video->expires_at?->isPast())) return false;
        if ($video->visibility === 'public') return true;

        return $video->visibility === 'followers' && $user
            && $user->following()->whereKey($video->user_id)->exists();
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
