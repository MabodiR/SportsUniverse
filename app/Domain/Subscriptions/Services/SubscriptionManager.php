<?php

namespace App\Domain\Subscriptions\Services;

use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Models\SubscriptionPayment;
use App\Domain\Subscriptions\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubscriptionManager
{
    public function activate(SubscriptionPayment $payment): Subscription
    {
        return DB::transaction(function () use ($payment) {
            $payment = SubscriptionPayment::query()->lockForUpdate()->findOrFail($payment->id);
            $existing = Subscription::query()->where('provider_reference', $payment->merchant_reference)->first();
            if ($existing) return $existing;

            $current = $payment->user->subscriptions()->current()->latest('starts_at')->lockForUpdate()->first();
            $payment->user->subscriptions()->where('status', 'scheduled')->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $startsAt = now();
            if ($current && $current->plan->monthly_price_cents > $payment->plan->monthly_price_cents && $current->ends_at?->isFuture()) {
                $startsAt = $current->ends_at;
            } elseif ($current) {
                $current->update(['status' => 'replaced', 'ends_at' => now()]);
            }

            return Subscription::create([
                'user_id' => $payment->user_id, 'subscription_plan_id' => $payment->subscription_plan_id,
                'status' => $startsAt->isFuture() ? 'scheduled' : 'active',
                'billing_interval' => $payment->billing_interval, 'provider' => 'payfast',
                'provider_reference' => $payment->merchant_reference, 'starts_at' => $startsAt,
                'ends_at' => $payment->billing_interval === 'annual' ? $startsAt->copy()->addYear() : $startsAt->copy()->addMonth(),
            ]);
        });
    }

    public function downgradeToFree(User $user, SubscriptionPlan $free): Subscription
    {
        return DB::transaction(function () use ($user, $free) {
            $current = $user->subscriptions()->current()->latest('starts_at')->lockForUpdate()->first();
            $startsAt = $current?->ends_at?->isFuture() ? $current->ends_at : now();
            $user->subscriptions()->where('status', 'scheduled')->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            if (! $startsAt->isFuture() && $current) $current->update(['status' => 'replaced', 'ends_at' => now()]);
            return Subscription::create([
                'user_id' => $user->id, 'subscription_plan_id' => $free->id,
                'status' => $startsAt->isFuture() ? 'scheduled' : 'active', 'billing_interval' => 'monthly',
                'starts_at' => $startsAt,
            ]);
        });
    }
}
