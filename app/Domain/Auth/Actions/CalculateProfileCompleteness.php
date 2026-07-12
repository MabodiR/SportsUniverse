<?php

namespace App\Domain\Auth\Actions;

use App\Models\User;

class CalculateProfileCompleteness
{
    public function execute(User $user): int
    {
        $user->load('profile', 'athleteProfile', 'fanProfile', 'roles');
        $score = 20;
        if ($user->roles->isNotEmpty()) {
            $score += 15;
        }
        $roleDetails = $user->hasRole('athlete') ? $user->athleteProfile : ($user->hasRole('fan') ? $user->fanProfile : null);
        if ($roleDetails && collect($roleDetails->getAttributes())->except(['id', 'user_id', 'created_at', 'updated_at'])->filter()->isNotEmpty()) {
            $score += 20;
        }
        if ($user->profile && ($user->profile->date_of_birth || $user->profile->country || $user->profile->city)) {
            $score += 20;
        }
        if ($user->profile?->profile_image_path) {
            $score += 15;
        }
        if ($user->profile?->bio) {
            $score += 10;
        }
        $user->profile()->updateOrCreate([], ['completeness' => min($score, 100)]);

        return min($score, 100);
    }
}
