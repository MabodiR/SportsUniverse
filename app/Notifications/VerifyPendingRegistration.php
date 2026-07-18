<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyPendingRegistration extends Notification
{
    use Queueable;

    public function __construct(private readonly string $name, private readonly string $verificationUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your SportUniverse email address')
            ->greeting('Hello '.$this->name.',')
            ->line('Confirm your email address to finish creating your SportUniverse account.')
            ->action('Verify email and create account', $this->verificationUrl)
            ->line('This verification link expires in 60 minutes. If you did not request this account, you can ignore this email.');
    }
}
