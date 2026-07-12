<?php

namespace App\Domain\Notifications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['messages' => 'boolean', 'message_requests' => 'boolean', 'opportunities' => 'boolean', 'followers' => 'boolean', 'engagement' => 'boolean', 'moderation' => 'boolean', 'profile_views' => 'boolean', 'email_digest' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
