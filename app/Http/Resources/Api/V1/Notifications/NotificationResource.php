<?php

namespace App\Http\Resources\Api\V1\Notifications;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'category' => $this->data['category'] ?? 'system', 'data' => $this->data, 'read_at' => $this->read_at, 'created_at' => $this->created_at];
    }
}
