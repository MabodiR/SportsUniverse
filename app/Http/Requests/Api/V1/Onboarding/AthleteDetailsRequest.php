<?php

namespace App\Http\Requests\Api\V1\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class AthleteDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('athlete') ?? false;
    }

    public function rules(): array
    {
        return ['primary_sport' => ['nullable', 'string', 'max:100'], 'position' => ['nullable', 'string', 'max:100'], 'club_name' => ['nullable', 'string', 'max:160'], 'playing_level' => ['nullable', 'string', 'max:40'], 'dominant_side' => ['nullable', 'string', 'max:20'], 'bio' => ['nullable', 'string', 'max:1000']];
    }
}
