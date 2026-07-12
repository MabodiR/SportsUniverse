<?php

namespace App\Http\Resources\Api\V1\Feed;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->public_id, 'caption' => $this->caption, 'hashtags' => $this->hashtags ?? [], 'visibility' => $this->visibility, 'status' => $this->status, 'published_at' => $this->published_at, 'creator' => ['id' => $this->user->id, 'name' => $this->user->name, 'slug' => $this->user->profile?->slug, 'profile_image' => $this->user->profile?->profile_image_path], 'sport' => $this->sport?->only(['id', 'name', 'slug']), 'media' => ['id' => $this->media->public_id, 'mime_type' => $this->media->mime_type, 'duration_ms' => $this->media->duration_ms, 'width' => $this->media->width, 'height' => $this->media->height, 'download_url' => route('media.download', $this->media)], 'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => ['id' => $image->public_id, 'download_url' => route('media.download', $image), 'is_cover' => (bool) $image->pivot->is_cover, 'position' => $image->pivot->position])->values()), 'counts' => ['views' => $this->views_count, 'likes' => $this->likes_count, 'comments' => $this->comments_count, 'shares' => $this->shares_count, 'saves' => $this->saves_count], 'viewer' => ['liked' => (bool) ($this->liked_by_viewer_exists ?? false), 'saved' => (bool) ($this->saved_by_viewer_exists ?? false), 'following_creator' => (bool) ($this->creator_followed_by_viewer_exists ?? false)]];
    }
}
