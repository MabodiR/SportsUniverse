<?php

namespace App\Domain\Live\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class LiveActivity implements ShouldBroadcastNow
{
    public function __construct(public string $streamId, public array $activity) {}

    public function broadcastOn(): array
    {
        return [new Channel('live.'.$this->streamId)];
    }

    public function broadcastAs(): string
    {
        return 'live.activity';
    }

    public function broadcastWith(): array
    {
        return $this->activity;
    }
}
