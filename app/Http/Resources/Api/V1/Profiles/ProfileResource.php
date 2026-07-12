<?php

namespace App\Http\Resources\Api\V1\Profiles;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->profile;

        return [
            'id' => $this->id,
            'slug' => $profile->slug,
            'name' => $this->name,
            'roles' => $this->roles->pluck('name'),
            'bio' => $profile->bio,
            'date_of_birth' => $this->when($request->user()?->id === $this->id, $profile->date_of_birth?->toDateString()),
            'age' => $profile->date_of_birth?->age,
            'gender' => $profile->gender,
            'location' => ['country' => $profile->country, 'province' => $profile->province, 'city' => $profile->city, 'locality' => $profile->locality, 'township' => $profile->township],
            'images' => ['profile' => $profile->profile_image_path, 'cover' => $profile->cover_image_path],
            'is_available' => $profile->is_available,
            'views_count' => (int) $profile->views_count,
            'completeness' => $this->when($request->user()?->id === $this->id || $request->user()?->hasRole('admin'), $profile->completeness),
            'athlete' => $this->when($this->relationLoaded('athleteProfile') && $this->athleteProfile, fn () => ['sport' => $this->athleteProfile->sport?->only(['id', 'name', 'slug']), 'position' => $this->athleteProfile->taxonomyPosition?->only(['id', 'name', 'slug']), 'club_name' => $this->athleteProfile->club_name, 'playing_level' => $this->athleteProfile->playing_level, 'dominant_side' => $this->athleteProfile->dominant_side, 'height_cm' => $this->athleteProfile->height_cm, 'weight_kg' => $this->athleteProfile->weight_kg]),
            'fan' => $this->when($this->relationLoaded('fanProfile') && $this->fanProfile, fn () => ['interested_sports' => $this->fanProfile->interested_sports ?? [], 'favourites' => $this->fanProfile->favourites]),
            'professional' => $this->when($this->relationLoaded('professionalProfile') && $this->professionalProfile, fn () => $this->professionalProfile?->only(['professional_type', 'specialisation', 'years_experience', 'certifications', 'is_available'])),
            'organisation' => $this->when($this->relationLoaded('organisationProfile') && $this->organisationProfile, fn () => $this->organisationProfile?->only(['organisation_name', 'organisation_type', 'website', 'contact_email', 'contact_phone', 'services'])),
        ];
    }
}
