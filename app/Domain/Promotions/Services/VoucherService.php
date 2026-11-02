<?php

namespace App\Domain\Promotions\Services;

use App\Domain\Promotions\Models\PromotionAnnouncement;
use App\Domain\Promotions\Models\Voucher;
use App\Domain\Promotions\Models\VoucherRedemption;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\VoucherMilestoneReached;
use App\Notifications\VoucherRedeemed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    public function findAvailable(?string $code = null): ?Voucher
    {
        $query = Voucher::query()->with('plans')->where('is_active', true)->where('is_archived', false)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('max_redemptions')->orWhereColumn('redemptions_count', '<', 'max_redemptions'));

        return $code
            ? $query->whereRaw('LOWER(code) = ?', [mb_strtolower(trim($code))])->first()
            : $query->where('auto_apply', true)->orderBy('id')->first();
    }

    public function validateCode(string $code): Voucher
    {
        return $this->findAvailable($code) ?? throw ValidationException::withMessages(['voucher_code' => 'This voucher is invalid, expired, inactive or fully redeemed.']);
    }

    public function redeemForNewUser(User $user, ?string $code = null): ?VoucherRedemption
    {
        return DB::transaction(function () use ($user, $code) {
            $candidate = $this->findAvailable($code);
            if (! $candidate) {
                return null;
            }

            $voucher = Voucher::query()->with('plans')->lockForUpdate()->find($candidate->id);
            if (! $voucher?->available()) {
                return null;
            }
            if ($voucher->redemptions()->where('user_id', $user->id)->count() >= $voucher->max_redemptions_per_user) {
                return null;
            }

            $plan = $voucher->plans->sortBy('monthly_price_cents')->first()
                ?? SubscriptionPlan::query()->where('is_active', true)->where('monthly_price_cents', '>', 0)->orderBy('monthly_price_cents')->first();
            if (! $plan) {
                return null;
            }

            $original = $plan->monthly_price_cents;
            $discount = match ($voucher->discount_type) {
                'percentage' => min($original, (int) round($original * min(100, $voucher->discount_value) / 100)),
                'fixed' => min($original, $voucher->discount_value),
                default => $original,
            };
            if ($original - $discount > 0) {
                return null;
            }
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'billing_interval' => 'monthly',
                'provider' => 'voucher',
                'provider_reference' => "voucher:{$voucher->id}:user:{$user->id}:".str()->uuid(),
                'starts_at' => now(),
                'ends_at' => $voucher->subscriptionEndsAt(),
            ]);
            $redemption = VoucherRedemption::create([
                'voucher_id' => $voucher->id, 'user_id' => $user->id, 'subscription_plan_id' => $plan->id,
                'subscription_id' => $subscription->id, 'original_amount_cents' => $original,
                'discount_amount_cents' => $discount, 'final_amount_cents' => max(0, $original - $discount), 'redeemed_at' => now(),
            ]);
            $voucher->increment('redemptions_count');
            $count = (int) $voucher->redemptions_count;
            DB::afterCommit(function () use ($user, $redemption, $voucher, $count) {
                $user->notify(new VoucherRedeemed($redemption->id));
                if (in_array($count, [50, 100, 150, 190, 200], true)) {
                    User::role(['admin', 'system_admin', 'super_admin'])->each(fn (User $admin) => $admin->notify(new VoucherMilestoneReached($voucher->campaign_name ?: $voucher->code, $count, $voucher->max_redemptions ?: $count)));
                }
            });

            return $redemption->load('voucher', 'plan', 'subscription');
        }, 3);
    }

    public function publicPromotion(): ?array
    {
        if (! Schema::hasTable('promotion_announcements')) {
            return null;
        }
        $announcement = PromotionAnnouncement::query()->with('voucher.plans')->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latest()->first();
        if (! $announcement || ! $announcement->voucher?->available()) {
            return null;
        }
        $voucher = $announcement->voucher;
        $plan = $voucher->plans->sortBy('monthly_price_cents')->first();

        $message = strtr($announcement->message, [
            ':limit' => (string) ($voucher->max_redemptions ?? 'unlimited'),
            ':remaining' => (string) ($voucher->remaining ?? 'unlimited'),
            ':duration' => $voucher->duration_label,
            ':plan' => $plan?->name ?? 'membership',
        ]);

        return ['id' => $announcement->id, 'title' => $announcement->title, 'message' => $message, 'cta_label' => $announcement->cta_label, 'cta_url' => $announcement->cta_url, 'show_banner' => $announcement->show_banner, 'show_popup' => $announcement->show_popup, 'code' => $voucher->code, 'remaining' => $voucher->remaining, 'limit' => $voucher->max_redemptions, 'plan' => $plan?->name, 'duration_label' => $voucher->duration_label, 'duration_value' => (int) $voucher->membership_duration_value, 'duration_unit' => $voucher->membership_duration_unit, 'original_price_cents' => $plan?->monthly_price_cents];
    }
}
