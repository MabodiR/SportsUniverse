<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['athlete', 'fan'] as $role) {
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

    public function test_onboarding_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/onboarding/completeness')->assertUnauthorized();
    }
}
