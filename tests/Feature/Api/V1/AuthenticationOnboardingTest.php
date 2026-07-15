<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class AuthenticationOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['athlete', 'fan', 'referee', 'linesman'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_user_can_register_and_receive_a_sanctum_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', ['name' => 'Ada Athlete', 'email' => 'ada@example.com', 'password' => 'Password9', 'password_confirmation' => 'Password9', 'device_name' => 'ios']);
        $response->assertCreated()->assertJsonPath('data.name', 'Ada Athlete')->assertJsonStructure(['token', 'data' => ['id', 'profile']]);
        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
        $this->assertDatabaseHas('user_profiles', ['completeness' => 20]);
    }

    public function test_athlete_can_complete_role_details_and_location_incrementally(): void
    {
        $registration = $this->postJson('/api/v1/auth/register', ['name' => 'Ada Athlete', 'email' => 'ada@example.com', 'password' => 'Password9', 'password_confirmation' => 'Password9']);
        $token = $registration->json('token');
        $headers = ['Authorization' => 'Bearer '.$token];
        $this->putJson('/api/v1/onboarding/role', ['role' => 'athlete'], $headers)->assertOk()->assertJsonPath('data.roles.0', 'athlete');
        $this->putJson('/api/v1/onboarding/athlete-details', ['primary_sport' => 'Football', 'position' => 'Striker', 'bio' => 'Fast forward.'], $headers)->assertOk();
        $this->putJson('/api/v1/onboarding/location', ['country' => 'ZA', 'province' => 'Gauteng', 'city' => 'Johannesburg'], $headers)->assertOk();
        $this->getJson('/api/v1/onboarding/completeness', $headers)->assertOk()->assertJsonPath('data.percentage', 85)->assertJsonPath('data.can_continue', true);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->postJson('/api/v1/auth/login', ['login' => 'missing@example.com', 'password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('login');
    }

    public function test_password_reset_request_has_a_generic_response(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'member@example.com']);

        foreach (['member@example.com', 'missing@example.com'] as $email) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => $email])
                ->assertOk()->assertJsonPath('message', 'If an account exists for that email address, a password reset link has been sent.');
        }
    }

    public function test_authenticated_user_can_change_password_with_current_password(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword9']);
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/password', [
            'current_password' => 'OldPassword9',
            'password' => 'NewPassword9',
            'password_confirmation' => 'NewPassword9',
        ])->assertOk();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPassword9', $user->fresh()->password));
    }

    public function test_password_change_rejects_incorrect_current_password(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword9']);
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/password', [
            'current_password' => 'WrongPassword9',
            'password' => 'NewPassword9',
            'password_confirmation' => 'NewPassword9',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
    }

    public function test_mobile_social_code_can_only_be_exchanged_once(): void
    {
        $user = User::factory()->create();
        $user->profile()->create(['completeness' => 20]);
        $code = str_repeat('a', 64);
        Cache::put('mobile-social:'.$code, ['user_id' => $user->id, 'device_name' => 'ios-mobile'], now()->addMinute());

        $this->postJson('/api/v1/auth/social/exchange', ['code' => $code, 'device_name' => 'ios-mobile'])
            ->assertOk()->assertJsonStructure(['token', 'data' => ['id']]);
        $this->postJson('/api/v1/auth/social/exchange', ['code' => $code])
            ->assertUnprocessable();
    }

    public function test_onboarding_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/onboarding/completeness')->assertUnauthorized();
    }

    public function test_referee_and_linesman_roles_create_professional_profiles(): void
    {
        foreach (['referee', 'linesman'] as $role) {
            $user = \App\Models\User::factory()->create(['email' => $role.'@example.com']);
            $user->profile()->create(['completeness' => 20]);
            \Laravel\Sanctum\Sanctum::actingAs($user);
            $this->patchJson('/api/v1/profile/role', ['role' => $role])->assertOk()->assertJsonPath('data.roles.0', $role)->assertJsonPath('data.professional.professional_type', $role);
            $this->assertDatabaseHas('professional_profiles', ['professional_type' => $role]);
        }
    }

    public function test_api_onboarding_accepts_official_roles_and_creates_professional_details(): void
    {
        foreach (['referee', 'linesman'] as $role) {
            $user = \App\Models\User::factory()->create(['email' => 'onboarding-'.$role.'@example.com']);
            $user->profile()->create(['completeness' => 20]);
            \Laravel\Sanctum\Sanctum::actingAs($user);

            $this->putJson('/api/v1/onboarding/role', ['role' => $role])
                ->assertOk()->assertJsonPath('data.roles.0', $role);
            $this->assertDatabaseHas('professional_profiles', ['user_id' => $user->id, 'professional_type' => $role]);
        }
    }
}
