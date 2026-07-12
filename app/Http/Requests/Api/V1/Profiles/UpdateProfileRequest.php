<?php

namespace App\Http\Requests\Api\V1\Profiles;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['sometimes', 'string', 'max:120'], 'date_of_birth' => ['sometimes', 'nullable', 'date', 'before:today'], 'gender' => ['sometimes', 'nullable', 'string', 'max:32'], 'bio' => ['sometimes', 'nullable', 'string', 'max:1000'], 'country' => ['sometimes', 'nullable', 'string', 'size:2'], 'province' => ['sometimes', 'nullable', 'string', 'max:120'], 'city' => ['sometimes', 'nullable', 'string', 'max:120'], 'locality' => ['sometimes', 'nullable', 'string', 'max:120'], 'township' => ['sometimes', 'nullable', 'string', 'max:120'], 'is_public' => ['sometimes', 'boolean'], 'is_available' => ['sometimes', 'boolean']];
    }
}
