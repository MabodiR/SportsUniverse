<?php

namespace App\Domain\Auth\Actions;

use App\Models\User;

class CalculateProfileCompleteness
{
    public function execute(User $user): int
    {
        $user->load('profile', 'athleteProfile', 'fanProfile', 'professionalProfile', 'organisationProfile', 'roles');
        $score = 20;
        if ($user->roles->isNotEmpty()) {
            $score += 15;
        }
        $roleDetails = match (true) {
            $user->hasRole('athlete') => $user->athleteProfile,
            $user->hasRole('fan') => $user->fanProfile,
            $user->hasAnyRole(['coach', 'referee', 'linesman', 'scout', 'agent']) => $user->professionalProfile,
            $user->hasAnyRole(['club', 'academy', 'business', 'sponsor']) => $user->organisationProfile,
            default => null,
        };
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
