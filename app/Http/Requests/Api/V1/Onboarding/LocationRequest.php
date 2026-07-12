<?php

namespace App\Http\Requests\Api\V1\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['date_of_birth' => ['nullable', 'date', 'before:today'], 'gender' => ['nullable', 'string', 'max:32'], 'country' => ['nullable', 'string', 'size:2'], 'province' => ['nullable', 'string', 'max:120'], 'city' => ['nullable', 'string', 'max:120'], 'locality' => ['nullable', 'string', 'max:120'], 'township' => ['nullable', 'string', 'max:120']];
    }
}
