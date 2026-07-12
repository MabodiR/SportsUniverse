<?php

namespace App\Http\Resources\Api\V1\Messaging;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->public_id, 'sender' => $this->sender ? ['id' => $this->sender->id, 'name' => $this->sender->name, 'slug' => $this->sender->profile?->slug] : null, 'body' => $this->deleted_at ? null : $this->body, 'media' => $this->when($this->media, fn () => ['id' => $this->media->public_id, 'kind' => $this->media->kind, 'mime_type' => $this->media->mime_type, 'download_url' => route('media.download', $this->media)]), 'read_at'=>$this->read_at??null,'edited_at' => $this->edited_at, 'deleted_at' => $this->deleted_at, 'created_at' => $this->created_at];
    }
}
