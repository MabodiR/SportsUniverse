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
            ->subject('Verify your SportsUniverse email address')
            ->greeting('Your next chapter starts here.')
            ->line('Hi '.$this->name.', welcome to SportsUniverse.')
            ->line('Confirm your email to create your account, find your community and discover your next sporting opportunity.')
            ->action('Verify email and create account', $this->verificationUrl)
            ->line('This verification link expires in 60 minutes. If you did not request this account, you can ignore this email.');
    }
}
