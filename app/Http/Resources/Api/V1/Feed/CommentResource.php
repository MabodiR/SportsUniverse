<?php

namespace App\Http\Resources\Api\V1\Feed;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->public_id, 'body' => $this->body, 'user' => ['id' => $this->user->id, 'name' => $this->user->name, 'slug' => $this->user->profile?->slug], 'parent_id' => $this->parent?->public_id, 'likes_count' => $this->likes_count, 'liked' => (bool) ($this->liked_by_viewer ?? false), 'replies' => CommentResource::collection($this->whenLoaded('replies')), 'created_at' => $this->created_at];
    }
}
