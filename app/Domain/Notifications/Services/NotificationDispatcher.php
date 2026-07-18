<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Notifications\SportUniverseNotification;
use App\Jobs\SendExpoPushNotification;
use App\Models\User;
use Throwable;

class NotificationDispatcher
{
    public function send(User $recipient, string $category, array $payload): void
    {
        $preferences = $recipient->notificationPreference()->firstOrCreate([])->refresh();
        if (! $preferences->{$category}) {
            return;
        }

        // Realtime broadcasting and mobile push are secondary effects. A stopped
        // Reverb or Redis process must never make the user's primary action fail.
        try {
            $recipient->notify(new SportUniverseNotification($category, $payload));
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            SendExpoPushNotification::dispatch($recipient->id, $category, $payload);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
