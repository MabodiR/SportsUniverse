<?php

namespace App\Http\Requests\Api\V1\Notifications;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['messages' => ['sometimes', 'boolean'], 'message_requests' => ['sometimes', 'boolean'], 'opportunities' => ['sometimes', 'boolean'], 'followers' => ['sometimes', 'boolean'], 'engagement' => ['sometimes', 'boolean'], 'moderation' => ['sometimes', 'boolean'], 'profile_views' => ['sometimes', 'boolean'], 'email_digest' => ['sometimes', 'boolean']];
    }
}
