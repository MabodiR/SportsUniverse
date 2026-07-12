<?php

namespace App\Http\Requests\Api\V1\Opportunities;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['reviewing', 'shortlisted', 'accepted', 'rejected'])], 'reviewer_notes' => ['nullable', 'string', 'max:5000']];
    }
}
