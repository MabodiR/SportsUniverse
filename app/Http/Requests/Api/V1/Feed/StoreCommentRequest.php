<?php

namespace App\Http\Requests\Api\V1\Feed;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:2000'], 'parent_id' => ['nullable', 'string', 'exists:comments,public_id']];
    }
}
