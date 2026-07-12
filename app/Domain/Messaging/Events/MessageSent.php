<?php

namespace App\Domain\Messaging\Events;

use App\Domain\Messaging\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable,SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversations.'.$this->message->conversation->public_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return ['id' => $this->message->public_id, 'conversation_id' => $this->message->conversation->public_id, 'sender_id' => $this->message->sender_id, 'body' => $this->message->body, 'media'=>$this->message->media?['id'=>$this->message->media->public_id,'kind'=>$this->message->media->kind,'mime_type'=>$this->message->media->mime_type,'download_url'=>route('media.download',$this->message->media)]:null,'created_at' => $this->message->created_at?->toAtomString()];
    }
}
