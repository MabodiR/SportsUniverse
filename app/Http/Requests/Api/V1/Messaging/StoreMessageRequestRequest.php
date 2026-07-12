<?php

namespace App\Http\Requests\Api\V1\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['recipient_id' => ['required', 'integer', 'exists:users,id', 'not_in:'.$this->user()?->id], 'message' => ['required', 'string', 'max:2000']];
    }
}
