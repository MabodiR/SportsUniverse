<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['verification_channel' => ['nullable', Rule::in(['email', 'whatsapp'])], 'name' => ['required', 'string', 'max:120'], 'email' => ['required_without:phone', 'nullable', 'email', 'max:255', 'unique:users,email'], 'phone' => ['required_without:email', 'nullable', 'string', 'max:32', 'unique:users,phone'], 'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->symbols()], 'role' => ['nullable', Rule::in(['athlete', 'fan', 'coach', 'referee', 'linesman', 'scout', 'agent', 'club', 'academy', 'business', 'sponsor'])], 'voucher_code' => ['nullable', 'string', 'max:80'], 'device_name' => ['nullable', 'string', 'max:120']];
    }
}
