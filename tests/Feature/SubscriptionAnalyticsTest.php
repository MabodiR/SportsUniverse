<?php

namespace Tests\Feature;

use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Models\SubscriptionPlan;
use App\Filament\Widgets\SubscriptionStats;
use App\Models\User;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubscriptionAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'system_admin', 'super_admin'] as $role) Role::findOrCreate($role, 'web');
        $this->seed(SubscriptionPlanSeeder::class);
    }

    public function test_subscription_analytics_is_restricted_to_system_owners(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->get('/admin/subscription-analytics')->assertForbidden();

        $owner = User::factory()->create();
        $owner->assignRole('super_admin');
        $this->actingAs($owner)->get('/admin/subscription-analytics')->assertOk()->assertSee('Subscription analytics');
    }

    public function test_subscription_stats_show_current_users_per_plan(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('super_admin');
        foreach (['free' => 4, 'pro' => 3, 'elite' => 2] as $slug => $count) {
            $plan = SubscriptionPlan::where('slug', $slug)->firstOrFail();
            User::factory()->count($count)->create()->each(fn ($user) => Subscription::create([
                'user_id' => $user->id, 'subscription_plan_id' => $plan->id, 'status' => 'active',
                'billing_interval' => 'monthly', 'starts_at' => now(),
            ]));
        }

        $this->actingAs($owner);
        Livewire::test(SubscriptionStats::class)
            ->assertSee('Free members')->assertSee('4')
            ->assertSee('Pro members')->assertSee('3')
            ->assertSee('Elite members')->assertSee('2');
    }
}
