<?php

namespace App\Http\Requests\Api\V1\Feed;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['media_id' => ['required', 'string', 'exists:media,public_id'], 'sport_id' => ['nullable', 'exists:sports,id'], 'caption' => ['nullable', 'string', 'max:2200'], 'hashtags' => ['nullable', 'array', 'max:20'], 'hashtags.*' => ['string', 'max:60'], 'visibility' => ['nullable', Rule::in(['public', 'followers', 'private'])], 'publish' => ['nullable', 'boolean']];
    }
}
