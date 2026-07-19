<?php

namespace Tests\Feature;

use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Models\SubscriptionPlan;
use App\Domain\Subscriptions\Models\SubscriptionPayment;
use App\Domain\Subscriptions\Services\SubscriptionEntitlements;
use App\Domain\Advertising\Services\PayFastGateway;
use App\Models\User;
use Database\Seeders\SubscriptionPlanSeeder;
use Database\Seeders\AssignExistingUsersToSubscriptionPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_membership_page_contains_three_managed_plans(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/membership')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Membership/Index')->has('plans', 3)->where('currentPlan', 'free'));
    }

    public function test_active_subscription_controls_entitlements(): void
    {
        $user = User::factory()->create();
        $elite = SubscriptionPlan::where('slug', 'elite')->firstOrFail();
        Subscription::create(['user_id'=>$user->id,'subscription_plan_id'=>$elite->id,'status'=>'active','billing_interval'=>'monthly','starts_at'=>now()]);

        $entitlements = app(SubscriptionEntitlements::class);

        $this->assertSame('elite', $entitlements->plan($user)->slug);
        $this->assertSame(100000, $entitlements->limit($user, 'live_viewers'));
        $this->assertTrue($entitlements->allows($user, 'branded_live'));
    }

    public function test_member_can_start_a_signed_payfast_plan_checkout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/membership/plans/pro/checkout', ['billing_interval' => 'monthly'])
            ->assertOk()->assertJsonPath('data.checkout.provider', 'payfast')
            ->assertJsonPath('data.checkout.fields.amount', '149.00');

        $this->assertDatabaseHas('subscription_payments', ['user_id' => $user->id, 'amount_cents' => 14900, 'status' => 'pending']);
    }

    public function test_verified_payfast_notification_activates_membership_once(): void
    {
        config(['payfast.validate_ip' => false, 'payfast.validate_server' => false]);
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/v1/membership/plans/pro/checkout', ['billing_interval' => 'monthly'])->assertOk();
        $payment = SubscriptionPayment::firstOrFail();
        $payload = ['merchant_id' => (string) config('payfast.merchant_id'), 'm_payment_id' => $payment->merchant_reference, 'amount_gross' => '149.00', 'payment_status' => 'COMPLETE', 'pf_payment_id' => 'PF-SUB-1'];
        $payload['signature'] = app(PayFastGateway::class)->signature($payload);

        $this->postJson('/api/v1/membership/payfast/notify', $payload)->assertOk();
        $this->postJson('/api/v1/membership/payfast/notify', $payload)->assertOk();

        $this->assertSame(1, Subscription::where('provider_reference', $payment->merchant_reference)->count());
        $this->assertSame('pro', app(SubscriptionEntitlements::class)->plan($user)->slug);
    }

    public function test_paid_member_can_schedule_free_downgrade(): void
    {
        $user = User::factory()->create();
        $pro = SubscriptionPlan::where('slug', 'pro')->firstOrFail();
        Subscription::create(['user_id' => $user->id, 'subscription_plan_id' => $pro->id, 'status' => 'active', 'billing_interval' => 'monthly', 'starts_at' => now(), 'ends_at' => now()->addMonth()]);

        $this->actingAs($user)->postJson('/api/v1/membership/plans/free/checkout', ['billing_interval' => 'monthly'])
            ->assertOk()->assertJsonPath('data.scheduled', true);

        $this->assertDatabaseHas('subscriptions', ['user_id' => $user->id, 'status' => 'scheduled', 'billing_interval' => 'monthly']);
        $this->assertSame('pro', app(SubscriptionEntitlements::class)->plan($user)->slug);
    }

    public function test_existing_user_assignment_is_repeatable_and_preserves_memberships(): void
    {
        $users = User::factory()->count(10)->create();
        $elite = SubscriptionPlan::where('slug', 'elite')->firstOrFail();
        Subscription::create(['user_id' => $users->first()->id, 'subscription_plan_id' => $elite->id, 'status' => 'active', 'billing_interval' => 'annual', 'starts_at' => now(), 'ends_at' => now()->addYear()]);

        $this->seed(AssignExistingUsersToSubscriptionPlansSeeder::class);
        $this->seed(AssignExistingUsersToSubscriptionPlansSeeder::class);

        $this->assertSame(10, Subscription::whereIn('status', ['active', 'scheduled'])->count());
        $this->assertSame('elite', app(SubscriptionEntitlements::class)->plan($users->first())->slug);
        $this->assertSame(9, Subscription::where('provider', 'platform_assignment')->count());
    }
}
