<?php

namespace App\Domain\Advertising\Models;

use App\Domain\Feed\Models\Video;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdCampaign extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['audience' => 'array', 'starts_on' => 'date', 'ends_on' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CampaignPayment::class, 'campaign_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CampaignDelivery::class, 'campaign_id');
    }
}
