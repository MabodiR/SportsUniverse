<?php

namespace App\Policies;

use App\Domain\Profiles\Models\UserProfile;
use App\Models\User;

class UserProfilePolicy
{
    public function view(?User $viewer, UserProfile $profile): bool
    {
        return $profile->is_public || $viewer?->id === $profile->user_id || $viewer?->hasRole('admin');
    }

    public function update(User $user, UserProfile $profile): bool
    {
        return $user->id === $profile->user_id || $user->hasRole('admin');
    }
}
