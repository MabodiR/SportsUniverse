<?php

namespace App\Domain\Advertising\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignDelivery extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['served_on' => 'date', 'served_at' => 'datetime', 'impressed_at' => 'datetime', 'clicked_at' => 'datetime',
            'video_viewed_at' => 'datetime', 'profile_visited_at' => 'datetime', 'followed_at' => 'datetime'];
    }

    public function getRouteKeyName(): string { return 'public_id'; }
    public function campaign(): BelongsTo { return $this->belongsTo(AdCampaign::class, 'campaign_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
