<?php

namespace App\Http\Requests\Api\V1\Feed;

use Illuminate\Foundation\Http\FormRequest;

class RecordViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['watched_ms' => ['nullable', 'integer', 'min:0', 'max:86400000'], 'completed' => ['nullable', 'boolean']];
    }
}
