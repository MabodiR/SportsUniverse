<?php

namespace Tests\Feature;

use App\Domain\Analytics\Services\UserAnalyticsService;
use App\Domain\Auth\Services\LoginHistoryRecorder;
use App\Domain\Sports\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'athlete', 'fan'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_admin_can_open_user_analytics_but_regular_members_cannot(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin)->get('/admin/user-analytics')->assertOk()->assertSee('Understand who is joining SportsUniverse');

        $fan = User::factory()->create();
        $fan->assignRole('fan');
        $this->actingAs($fan)->get('/admin/user-analytics')->assertForbidden();
    }

    public function test_dashboard_filters_real_user_profile_and_registration_source_data(): void
    {
        $football = Sport::create(['name' => 'Football', 'slug' => 'football', 'is_active' => true]);
        $mobile = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $mobile->assignRole('athlete');
        $mobile->profile()->create(['province' => 'Gauteng', 'city' => 'Johannesburg', 'gender' => 'female', 'date_of_birth' => now()->subYears(21), 'completeness' => 90]);
        $mobile->athleteProfile()->create(['sport_id' => $football->id]);
        DB::table('login_histories')->insert(['user_id' => $mobile->id, 'method' => 'registration', 'device_type' => 'mobile', 'logged_in_at' => now()]);

        $web = User::factory()->create(['status' => 'active']);
        $web->assignRole('fan');
        $web->profile()->create(['province' => 'Western Cape', 'city' => 'Cape Town', 'gender' => 'male', 'date_of_birth' => now()->subYears(38), 'completeness' => 40]);
        DB::table('login_histories')->insert(['user_id' => $web->id, 'method' => 'registration', 'device_type' => 'desktop', 'logged_in_at' => now()]);

        $analytics = app(UserAnalyticsService::class)->dashboard(['period' => 0]);
        $this->assertSame(2, $analytics['summary']['total']);
        $this->assertSame(1, $analytics['summary']['mobile']);
        $this->assertSame(1, $analytics['summary']['web']);
        $this->assertSame(50.0, $analytics['summary']['mobile_share']);

        $filtered = app(UserAnalyticsService::class)->dashboard(['period' => 0, 'source' => 'mobile', 'province' => 'Gauteng', 'sport' => $football->id, 'age_min' => 18, 'age_max' => 24]);
        $this->assertSame(1, $filtered['summary']['total']);
        $this->assertSame('Gauteng', $filtered['locations'][0]['label']);
        $this->assertSame('Football', $filtered['sports'][0]['label']);
    }

    public function test_mobile_device_name_overrides_generic_api_user_agent(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/api/v1/auth/register', 'POST', ['device_name' => 'ios-mobile']);
        $request->headers->set('User-Agent', 'Symfony');

        app(LoginHistoryRecorder::class)->record($user, $request, 'registration');

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'method' => 'registration',
            'device_type' => 'mobile',
            'platform' => 'iOS',
            'browser' => 'SportsUniverse',
        ]);
    }
}
