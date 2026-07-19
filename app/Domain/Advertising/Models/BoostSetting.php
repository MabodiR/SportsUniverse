<?php

namespace App\Domain\Advertising\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoostSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean', 'cpm_cents' => 'integer', 'organic_posts_between' => 'integer',
            'frequency_cap_per_day' => 'integer', 'minimum_daily_budget_cents' => 'integer',
            'maximum_daily_budget_cents' => 'integer', 'maximum_duration_days' => 'integer',
            'require_review' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function impressionCost(): int
    {
        return max(1, (int) ceil($this->cpm_cents / 1000));
    }
}
