<?php

namespace Tests\Feature;

use App\Domain\Promotions\Models\Voucher;
use App\Domain\Promotions\Services\VoucherService;
use App\Domain\Subscriptions\Models\SubscriptionPlan;
use App\Filament\Widgets\PromotionStats;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('super_admin', 'web');
    }

    public function test_promotion_stats_use_live_redemptions_and_ignore_inactive_campaigns(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $owner->assignRole('super_admin');
        $member = User::factory()->create();
        $plan = SubscriptionPlan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'tagline' => 'Pro',
            'monthly_price_cents' => 14900,
            'annual_price_cents' => 149000,
            'features' => [],
            'limits' => [],
            'is_active' => true,
        ]);
        $active = Voucher::create([
            'code' => 'LIVE100',
            'campaign_name' => 'Live campaign',
            'discount_type' => 'free_package',
            'discount_value' => 100,
            'max_redemptions' => 2,
            'redemptions_count' => 0,
            'is_active' => true,
            'auto_apply' => true,
        ]);
        $active->plans()->attach($plan);
        app(VoucherService::class)->redeemForNewUser($member);
        $active->update(['redemptions_count' => 99]);

        Voucher::create([
            'code' => 'INACTIVE',
            'campaign_name' => 'Inactive campaign',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'max_redemptions' => 500,
            'is_active' => false,
            'auto_apply' => true,
        ]);

        $this->actingAs($owner);
        Livewire::test(PromotionStats::class)
            ->assertSee('Live campaign')
            ->assertDontSee('Inactive campaign')
            ->assertSee('Promotion claims')
            ->assertSee('Places remaining')
            ->assertSee('50.0%')
            ->assertSee('Active promotional members');
    }
}
