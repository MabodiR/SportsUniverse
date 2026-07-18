<?php

namespace App\Domain\Messaging\Events;

use App\Domain\Messaging\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversations.'.$this->message->conversation->public_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->public_id,
            'body' => $this->message->deleted_at ? null : $this->message->body,
            'edited_at' => $this->message->edited_at?->toAtomString(),
            'deleted_at' => $this->message->deleted_at?->toAtomString(),
            'media' => $this->message->deleted_at ? null : ($this->message->media ? [
                'id' => $this->message->media->public_id,
                'kind' => $this->message->media->kind,
                'mime_type' => $this->message->media->mime_type,
                'download_url' => route('media.download', $this->message->media),
            ] : null),
        ];
    }
}
