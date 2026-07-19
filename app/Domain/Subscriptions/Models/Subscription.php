<?php

namespace App\Domain\Subscriptions\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = ['user_id', 'subscription_plan_id', 'status', 'billing_interval', 'provider', 'provider_reference', 'starts_at', 'ends_at', 'cancelled_at'];
    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'cancelled_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function plan(): BelongsTo { return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id'); }
    public function scopeCurrent($query) { return $query->where('status', 'active')->where(fn ($active) => $active->whereNull('ends_at')->orWhere('ends_at', '>', now())); }
}
