<?php

namespace App\Http\Requests\Api\V1\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['body' => ['required_without:media_id', 'nullable', 'string', 'max:5000'], 'media_id' => ['required_without:body', 'nullable', 'string', 'exists:media,public_id']];
    }
}
