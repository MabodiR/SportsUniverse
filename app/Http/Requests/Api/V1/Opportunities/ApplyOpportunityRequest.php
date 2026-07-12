<?php

namespace App\Http\Requests\Api\V1\Opportunities;

use Illuminate\Foundation\Http\FormRequest;

class ApplyOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['cover_letter' => ['nullable', 'string', 'max:5000'], 'resume_media_id' => ['nullable', 'string', 'exists:media,public_id']];
    }
}
