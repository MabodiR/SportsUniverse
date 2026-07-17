<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Notifications\SportUniverseNotification;
use App\Jobs\SendExpoPushNotification;
use App\Models\User;

class NotificationDispatcher
{
    public function send(User $recipient, string $category, array $payload): void
    {
        $preferences = $recipient->notificationPreference()->firstOrCreate([])->refresh();
        if (! $preferences->{$category}) {
            return;
        }$recipient->notify(new SportUniverseNotification($category, $payload));
        SendExpoPushNotification::dispatch($recipient->id, $category, $payload);
    }
}
