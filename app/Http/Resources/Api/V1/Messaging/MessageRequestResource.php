<?php

namespace App\Http\Resources\Api\V1\Messaging;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->public_id, 'sender' => ['id' => $this->sender->id, 'name' => $this->sender->name, 'slug' => $this->sender->profile?->slug], 'recipient' => ['id' => $this->recipient->id, 'name' => $this->recipient->name, 'slug' => $this->recipient->profile?->slug], 'message' => $this->message, 'status' => $this->status, 'conversation_id' => $this->conversation?->public_id, 'responded_at' => $this->responded_at, 'created_at' => $this->created_at];
    }
}
