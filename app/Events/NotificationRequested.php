<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class NotificationRequested
{
    use Dispatchable;

    public function __construct(public int $recipientId, public string $category, public array $payload) {}
}
