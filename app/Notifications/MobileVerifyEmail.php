<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;

class MobileVerifyEmail extends VerifyEmail
{
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute('api.verification.verify', now()->addMinutes(60), [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
        ]);
    }
}
