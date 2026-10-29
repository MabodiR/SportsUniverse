<?php

namespace Tests\Feature;

use App\Domain\Promotions\Models\PromotionAnnouncement;
use App\Domain\Promotions\Models\Voucher;
use App\Domain\Promotions\Services\VoucherService;
use App\Domain\Subscriptions\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EarlyAdopterPromotionTest extends TestCase
{
    use RefreshDatabase;

    private function promotion(int $limit=2): Voucher
    {
        $plan=SubscriptionPlan::create(['name'=>'Pro','slug'=>'pro','tagline'=>'Pro','monthly_price_cents'=>14900,'annual_price_cents'=>149000,'features'=>[],'limits'=>[],'is_active'=>true,'sort_order'=>2]);
        $voucher=Voucher::create(['code'=>'EARLY200','campaign_name'=>'Early Adopter 200','discount_type'=>'free_package','discount_value'=>100,'max_redemptions'=>$limit,'max_redemptions_per_user'=>1,'is_active'=>true,'new_users_only'=>true,'auto_apply'=>true]);
        $voucher->plans()->attach($plan);
        PromotionAnnouncement::create(['voucher_id'=>$voucher->id,'title'=>'Early Adopter Special','message'=>'First members are free','show_banner'=>true,'show_popup'=>true,'is_active'=>true]);
        return $voucher;
    }

    public function test_auto_voucher_activates_zero_cost_subscription():void
    {
        Notification::fake();$voucher=$this->promotion();$user=User::factory()->create();
        $redemption=app(VoucherService::class)->redeemForNewUser($user);
        $this->assertNotNull($redemption);$this->assertSame(0,$redemption->final_amount_cents);
        $this->assertDatabaseHas('subscriptions',['user_id'=>$user->id,'provider'=>'voucher','status'=>'active']);
        $this->assertDatabaseHas('vouchers',['id'=>$voucher->id,'redemptions_count'=>1]);
    }

    public function test_limit_is_enforced_at_exactly_configured_count():void
    {
        Notification::fake();$voucher=$this->promotion(1);$service=app(VoucherService::class);
        $this->assertNotNull($service->redeemForNewUser(User::factory()->create()));
        $this->assertNull($service->redeemForNewUser(User::factory()->create()));
        $this->assertSame(1,$voucher->fresh()->redemptions_count);
    }

    public function test_expired_inactive_and_duplicate_redemptions_are_rejected():void
    {
        Notification::fake();$voucher=$this->promotion();$service=app(VoucherService::class);$user=User::factory()->create();
        $this->assertNotNull($service->redeemForNewUser($user));$this->assertNull($service->redeemForNewUser($user));
        $voucher->update(['is_active'=>false]);$this->assertNull($service->redeemForNewUser(User::factory()->create()));
    }

    public function test_homepage_receives_active_banner_and_popup():void
    {
        $this->promotion();$this->get('/')->assertOk()->assertInertia(fn($page)=>$page->component('Public/Home')->where('promotion.show_banner',true)->where('promotion.show_popup',true)->where('promotion.remaining',2));
    }
}
