<?php

namespace App\Http\Requests\Api\V1\Discovery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchProfilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:120'], 'role' => ['nullable', Rule::in(['athlete', 'fan', 'coach', 'scout', 'agent', 'club', 'academy', 'business', 'sponsor'])], 'sport_id' => ['nullable', 'integer', 'exists:sports,id'], 'position_id' => ['nullable', 'integer', 'exists:positions,id'], 'min_age' => ['nullable', 'integer', 'between:5,100'], 'max_age' => ['nullable', 'integer', 'between:5,100', 'gte:min_age'], 'gender' => ['nullable', 'string', 'max:32'], 'country' => ['nullable', 'string', 'size:2'], 'province' => ['nullable', 'string', 'max:120'], 'city' => ['nullable', 'string', 'max:120'], 'locality' => ['nullable', 'string', 'max:120'], 'township' => ['nullable', 'string', 'max:120'], 'club' => ['nullable', 'string', 'max:160'], 'available' => ['nullable', 'boolean'], 'min_completeness' => ['nullable', 'integer', 'between:0,100'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'between:1,50']];
    }

    public function criteria(): array
    {
        return [...$this->validated(), 'page' => $this->integer('page', 1), 'per_page' => $this->integer('per_page', 20), ...($this->has('available') ? ['available' => $this->boolean('available')] : [])];
    }
}
