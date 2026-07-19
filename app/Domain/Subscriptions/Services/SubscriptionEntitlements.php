<?php

namespace App\Domain\Subscriptions\Services;

use App\Domain\Subscriptions\Models\SubscriptionPlan;
use App\Models\User;

class SubscriptionEntitlements
{
    public function plan(User $user): SubscriptionPlan
    {
        $user->subscriptions()->where('status', 'scheduled')->where('starts_at', '<=', now())->update(['status' => 'active']);
        $user->subscriptions()->where('status', 'active')->whereNotNull('ends_at')->where('ends_at', '<=', now())->update(['status' => 'expired']);

        return $user->subscriptions()->current()->with('plan')->latest('starts_at')->first()?->plan
            ?? SubscriptionPlan::query()->where('slug', 'free')->first()
            ?? new SubscriptionPlan(['name'=>'Free','slug'=>'free','limits'=>['live_viewers'=>1000,'storage_gb'=>5,'workspace_seats'=>1,'analytics_days'=>30,'live_hours_monthly'=>5,'data_exports'=>false,'branded_live'=>false]]);
    }

    public function limit(User $user, string $key, mixed $fallback = null): mixed { return data_get($this->plan($user)->limits, $key, $fallback); }
    public function allows(User $user, string $key): bool { return (bool) $this->limit($user, $key, false); }
}
