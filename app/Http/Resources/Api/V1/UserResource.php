<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'email_verified_at' => $this->email_verified_at, 'email_verified' => $this->hasVerifiedEmail(), 'phone' => $this->phone, 'status' => $this->status, 'roles' => $this->roles->pluck('name'), 'profile' => $this->whenLoaded('profile', fn () => ['date_of_birth' => $this->profile?->date_of_birth?->toDateString(), 'gender' => $this->profile?->gender, 'bio' => $this->profile?->bio, 'country' => $this->profile?->country, 'province' => $this->profile?->province, 'city' => $this->profile?->city, 'locality' => $this->profile?->locality, 'township' => $this->profile?->township, 'completeness' => $this->profile?->completeness ?? 20]), 'onboarding_completed_at' => $this->onboarding_completed_at];
    }
}
