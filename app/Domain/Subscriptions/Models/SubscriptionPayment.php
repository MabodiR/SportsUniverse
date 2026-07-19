<?php

namespace App\Domain\Subscriptions\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['provider_payload' => 'array', 'paid_at' => 'datetime']; }
    public function getRouteKeyName(): string { return 'public_id'; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function plan(): BelongsTo { return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id'); }
}
