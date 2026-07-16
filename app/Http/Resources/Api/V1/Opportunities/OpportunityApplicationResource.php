<?php

namespace App\Http\Resources\Api\V1\Opportunities;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->public_id, 'opportunity' => new OpportunityResource($this->whenLoaded('opportunity')), 'applicant' => ['id' => $this->user->id, 'name' => $this->user->name, 'slug' => $this->user->profile?->slug, 'image' => $this->user->profile?->profile_image_path], 'cover_letter' => $this->cover_letter, 'resume' => $this->resume ? ['id' => $this->resume->public_id, 'download_url' => route('media.download', $this->resume)] : null, 'documents' => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($document) => ['id' => $document->public_id, 'requirement_key' => $document->pivot->requirement_key, 'name' => $document->title ?: $document->original_name, 'collection' => $document->collection, 'download_url' => route('media.download', $document)])), 'status' => $this->status, 'reviewer_notes' => $this->when($request->user()?->id === $this->user_id || $request->user()?->id === $this->opportunity?->posted_by_id, $this->reviewer_notes), 'reviewed_at' => $this->reviewed_at, 'timeline' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($item) => ['status' => $item->status, 'notes' => $item->notes, 'created_at' => $item->created_at])), 'created_at' => $this->created_at];
    }
}
