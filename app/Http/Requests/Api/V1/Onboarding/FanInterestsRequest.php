<?php

namespace App\Http\Requests\Api\V1\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class FanInterestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('fan') ?? false;
    }

    public function rules(): array
    {
        return ['interested_sports' => ['nullable', 'array', 'max:20'], 'interested_sports.*' => ['string', 'max:100'], 'favourites' => ['nullable', 'string', 'max:1000'], 'notification_preferences' => ['nullable', 'array']];
    }
}
