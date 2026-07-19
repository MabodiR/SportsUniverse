<?php

namespace App\Domain\Subscriptions\Services;

use App\Domain\Advertising\Services\PayFastGateway;
use App\Domain\Subscriptions\Models\SubscriptionPayment;
use App\Domain\Subscriptions\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Str;

class SubscriptionPayFastGateway
{
    public function __construct(private PayFastGateway $security) {}

    public function checkout(SubscriptionPlan $plan, User $user, string $interval): array
    {
        abort_unless(config('payfast.enabled') && config('payfast.merchant_id') && config('payfast.merchant_key'), 503, 'PayFast payments are not configured.');
        $amount = $interval === 'annual' ? $plan->annual_price_cents : $plan->monthly_price_cents;
        abort_unless($amount > 0, 422, 'This plan does not require payment.');

        $payment = SubscriptionPayment::query()->whereBelongsTo($user)->whereBelongsTo($plan, 'plan')
            ->where('billing_interval', $interval)->where('status', 'pending')->latest()->first();
        if (! $payment) {
            $id = (string) Str::ulid();
            $payment = SubscriptionPayment::create([
                'public_id' => $id, 'user_id' => $user->id, 'subscription_plan_id' => $plan->id,
                'billing_interval' => $interval, 'merchant_reference' => 'SU-MEMBER-'.$id,
                'amount_cents' => $amount, 'status' => 'pending',
            ]);
        }

        $names = preg_split('/\s+/', trim((string) $user->name), 2);
        $data = [
            'merchant_id' => (string) config('payfast.merchant_id'),
            'merchant_key' => (string) config('payfast.merchant_key'),
            'return_url' => route('membership.payfast.return', $payment),
            'cancel_url' => route('membership.payfast.cancel', $payment),
            'notify_url' => route('membership.payfast.notify'),
            'name_first' => $names[0] ?: 'SportsUniverse', 'name_last' => $names[1] ?? 'Member',
            'email_address' => (string) $user->email, 'm_payment_id' => $payment->merchant_reference,
            'amount' => number_format($payment->amount_cents / 100, 2, '.', ''),
            'item_name' => Str::limit("SportsUniverse {$plan->name} membership", 100, ''),
            'item_description' => Str::limit(ucfirst($interval)." SportsUniverse {$plan->name} membership", 255, ''),
            'custom_str1' => $payment->public_id, 'custom_str2' => $plan->slug,
        ];
        $data['signature'] = $this->security->signature($data);

        return ['provider' => 'payfast', 'sandbox' => config('payfast.sandbox'), 'action' => config('payfast.process_url'), 'method' => 'POST', 'fields' => $data];
    }
}
