<?php

namespace App\Domain\Notifications\Listeners;

use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Events\NotificationRequested;
use App\Models\User;
use Throwable;

class SendRequestedNotification
{
    public function __construct(private NotificationDispatcher $notifications) {}

    public function handle(NotificationRequested $event): void
    {
        try {
            $recipient = User::find($event->recipientId);
            if ($recipient) $this->notifications->send($recipient, $event->category, $event->payload);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
