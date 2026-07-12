<?php

namespace App\Domain\Messaging\Notifications;

use App\Domain\Messaging\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends Notification
{
    use Queueable;

    public function __construct(public Message $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['category' => 'messages', 'event' => 'new_message', 'message_id' => $this->message->public_id, 'conversation_id' => $this->message->conversation->public_id, 'sender_id' => $this->message->sender_id, 'preview' => str($this->message->body)->limit(120)->value()];
    }
}
