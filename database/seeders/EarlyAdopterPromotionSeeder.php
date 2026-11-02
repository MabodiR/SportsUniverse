<?php

namespace Database\Seeders;

use App\Domain\Promotions\Models\PromotionAnnouncement;
use App\Domain\Promotions\Models\Voucher;
use App\Domain\Subscriptions\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class EarlyAdopterPromotionSeeder extends Seeder
{
    public function run(): void
    {
        $plan = SubscriptionPlan::query()->where('is_active', true)->where('monthly_price_cents', '>', 0)->orderBy('monthly_price_cents')->first();
        if (! $plan) {
            return;
        }
        $voucher = Voucher::query()->updateOrCreate(['code' => 'EARLY200'], ['campaign_name' => 'Early Adopter 200', 'description' => 'First 200 verified members receive the starter paid package free for one month.', 'discount_type' => 'free_package', 'discount_value' => 100, 'max_redemptions' => 200, 'max_redemptions_per_user' => 1, 'membership_duration_value' => 1, 'membership_duration_unit' => 'months', 'is_active' => true, 'new_users_only' => true, 'auto_apply' => true, 'is_archived' => false]);
        $voucher->plans()->sync([$plan->id]);
        PromotionAnnouncement::query()->updateOrCreate(['voucher_id' => $voucher->id], ['title' => 'Early Adopter Special', 'message' => 'The first :limit members receive our :plan package free for :duration.', 'cta_label' => 'Claim your free membership', 'cta_url' => '/register', 'show_banner' => true, 'show_popup' => true, 'is_active' => true]);
    }
}
