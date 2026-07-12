<?php

namespace App\Http\Requests\Api\V1\Moderation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['type' => ['required', Rule::in(['video', 'comment', 'media', 'user'])], 'id' => ['required', 'string', 'max:64'], 'reason' => ['required', Rule::in(['spam', 'harassment', 'hate', 'nudity', 'violence', 'fraud', 'impersonation', 'copyright', 'other'])], 'details' => ['nullable', 'string', 'max:5000']];
    }
}
