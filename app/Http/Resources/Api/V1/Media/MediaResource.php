<?php

namespace App\Http\Resources\Api\V1\Media;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->public_id, 'kind' => $this->kind, 'collection' => $this->collection, 'title' => $this->title, 'description' => $this->description, 'original_name' => $this->original_name, 'mime_type' => $this->mime_type, 'size_bytes' => $this->size_bytes, 'processing_status' => $this->processing_status, 'processing_error' => $this->when($request->user()?->id === $this->user_id, $this->processing_error), 'moderation_status' => $this->moderation_status, 'duration_ms' => $this->duration_ms, 'width' => $this->width, 'height' => $this->height, 'download_url' => route('media.download', $this->resource), 'created_at' => $this->created_at];
    }
}
