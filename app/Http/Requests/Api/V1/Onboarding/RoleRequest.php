<?php

namespace App\Http\Requests\Api\V1\Onboarding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['role' => ['required', Rule::in(['athlete', 'fan', 'coach', 'referee', 'linesman', 'scout', 'agent', 'club', 'academy', 'business', 'sponsor'])]];
    }
}
