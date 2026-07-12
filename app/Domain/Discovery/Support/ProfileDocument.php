<?php

namespace App\Domain\Discovery\Support;

use App\Models\User;

class ProfileDocument
{
    public function make(User $user): array
    {
        $user->load('roles', 'profile', 'athleteProfile.sport', 'athleteProfile.taxonomyPosition', 'professionalProfile', 'organisationProfile');

        return ['id' => $user->id, 'name' => $user->name, 'name_keyword' => mb_strtolower($user->name), 'slug' => $user->profile?->slug, 'roles' => $user->roles->pluck('name')->all(), 'bio' => $user->profile?->bio, 'date_of_birth' => $user->profile?->date_of_birth?->toDateString(), 'age' => $user->profile?->date_of_birth?->age, 'gender' => $user->profile?->gender, 'country' => $user->profile?->country, 'province' => $user->profile?->province, 'city' => $user->profile?->city, 'locality' => $user->profile?->locality, 'township' => $user->profile?->township, 'is_available' => (bool) $user->profile?->is_available, 'is_public' => (bool) $user->profile?->is_public, 'completeness' => (int) ($user->profile?->completeness ?? 0), 'sport_id' => $user->athleteProfile?->sport_id, 'sport' => $user->athleteProfile?->sport?->name, 'position_id' => $user->athleteProfile?->position_id, 'position' => $user->athleteProfile?->taxonomyPosition?->name, 'club' => $user->athleteProfile?->club_name, 'playing_level' => $user->athleteProfile?->playing_level, 'professional_type' => $user->professionalProfile?->professional_type, 'organisation' => $user->organisationProfile?->organisation_name, 'updated_at' => $user->updated_at?->toAtomString()];
    }
}
