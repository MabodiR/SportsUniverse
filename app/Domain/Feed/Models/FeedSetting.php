<?php

namespace App\Domain\Feed\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class FeedSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'view_weight' => 'float',
            'like_weight' => 'float',
            'comment_weight' => 'float',
            'share_weight' => 'float',
            'follow_boost' => 'float',
            'page_size' => 'integer',
            'recommendation_size' => 'integer',
            'use_fan_sports' => 'boolean',
        ];
    }

    public static function current(): self
    {
        // This is a singleton row. Avoid caching the Eloquent object itself because
        // serialized models can become incomplete during zero-downtime deployments.
        return static::query()->first() ?? static::query()->create();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    protected static function booted(): void
    {
        static::saved(function () {
            DB::table('recommendation_feed_items')->delete();
        });
    }
}
