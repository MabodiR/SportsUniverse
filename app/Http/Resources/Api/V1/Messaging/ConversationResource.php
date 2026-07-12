<?php

namespace App\Http\Resources\Api\V1\Messaging;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mine=$this->participants->firstWhere('id',$request->user()?->id)?->pivot;
        return ['id' => $this->public_id, 'type' => $this->type, 'participants' => $this->participants->map(fn ($user) => ['id' => $user->id, 'name' => $user->name, 'slug' => $user->profile?->slug, 'profile_image' => $user->profile?->profile_image_path]), 'last_message' => $this->whenLoaded('latestMessage', fn () => new MessageResource($this->latestMessage)), 'last_message_at' => $this->last_message_at, 'unread_count' => (int) ($this->unread_count ?? 0),'muted'=>(bool)$mine?->muted_at,'archived'=>(bool)$mine?->archived_at,'last_read_at'=>$mine?->last_read_at];
    }
}
