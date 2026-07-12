<?php

namespace App\Http\Requests\Api\V1\Profiles;

use App\Domain\Sports\Models\Position;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAthleteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('athlete') ?? false;
    }

    public function rules(): array
    {
        return ['sport_id' => ['sometimes', 'nullable', 'exists:sports,id'], 'position_id' => ['sometimes', 'nullable', 'exists:positions,id'], 'club_name' => ['sometimes', 'nullable', 'string', 'max:160'], 'playing_level' => ['sometimes', 'nullable', 'string', 'max:40'], 'dominant_side' => ['sometimes', 'nullable', 'string', 'max:20'], 'height_cm' => ['sometimes', 'nullable', 'integer', 'between:80,260'], 'weight_kg' => ['sometimes', 'nullable', 'numeric', 'between:20,300']];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($this->filled('position_id') && $this->filled('sport_id') && ! Position::whereKey($this->integer('position_id'))->where('sport_id', $this->integer('sport_id'))->exists()) {
                $validator->errors()->add('position_id', 'The position does not belong to the selected sport.');
            }
        }];
    }
}
