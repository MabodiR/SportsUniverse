<?php

namespace App\Http\Resources\Api\V1\Opportunities;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->public_id, 'title' => $this->title, 'type' => $this->type, 'description' => $this->description, 'poster' => ['id' => $this->poster->id, 'name' => $this->poster->organisationProfile?->organisation_name ?? $this->poster->name, 'slug' => $this->poster->profile?->slug], 'sport' => $this->sport?->only(['id', 'name', 'slug']), 'position' => $this->position?->only(['id', 'name', 'slug']), 'location' => ['country' => $this->country, 'province' => $this->province, 'city' => $this->city, 'is_remote' => $this->is_remote], 'age_range' => ['minimum' => $this->minimum_age, 'maximum' => $this->maximum_age], 'requirements' => $this->requirements ?? [], 'status' => $this->status, 'deadline' => $this->deadline, 'applications_count' => $this->applications_count, 'viewer' => ['saved' => (bool) ($this->saved_by_viewer ?? false), 'applied' => (bool) ($this->applied_by_viewer ?? false)], 'published_at' => $this->published_at, 'created_at' => $this->created_at];
    }
}
