<?php

namespace App\Http\Requests\Api\V1\Moderation;

use Illuminate\Foundation\Http\FormRequest;

class ModerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', 'string', 'max:40'], 'action' => ['nullable', 'string', 'max:50'], 'notes' => ['nullable', 'string', 'max:5000']];
    }
}
