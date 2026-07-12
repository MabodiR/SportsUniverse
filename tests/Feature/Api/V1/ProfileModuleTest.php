<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Profiles\Actions\EnsureProfileSlug;
use App\Domain\Sports\Models\Sport;
use App\Models\User;
use Database\Seeders\SportSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['athlete', 'fan', 'admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }$this->seed(SportSeeder::class);
    }

    public function test_sports_endpoint_returns_positions(): void
    {
        $this->getJson('/api/v1/sports')->assertOk()->assertJsonPath('data.0.name', 'Athletics')->assertJsonStructure(['data' => [['id', 'name', 'slug', 'positions']]]);
    }

    public function test_owner_can_update_general_and_athlete_profile(): void
    {
        $user = User::factory()->create();
        $user->profile()->create();
        $user->assignRole('athlete');
        $sport = Sport::where('slug', 'football')->firstOrFail();
        $position = $sport->positions()->where('slug', 'striker')->firstOrFail();
        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/profile', ['bio' => 'Academy forward', 'country' => 'ZA', 'city' => 'Pretoria', 'is_available' => true])->assertOk()->assertJsonPath('data.bio', 'Academy forward');
        $this->patchJson('/api/v1/profile/athlete', ['sport_id' => $sport->id, 'position_id' => $position->id, 'height_cm' => 181])->assertOk()->assertJsonPath('data.athlete.position.slug', 'striker');
    }

    public function test_position_must_belong_to_selected_sport(): void
    {
        $user = User::factory()->create();
        $user->profile()->create();
        $user->assignRole('athlete');
        $football = Sport::where('slug', 'football')->firstOrFail();
        $rugby = Sport::where('slug', 'rugby')->firstOrFail();
        $position = $rugby->positions()->firstOrFail();
        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/profile/athlete', ['sport_id' => $football->id, 'position_id' => $position->id])->assertUnprocessable()->assertJsonValidationErrors('position_id');
    }

    public function test_private_profile_is_hidden_from_other_users(): void
    {
        $owner = User::factory()->create();
        $owner->profile()->create(['is_public' => false]);
        $slug = app(EnsureProfileSlug::class)->execute($owner->load('profile'));
        $viewer = User::factory()->create();
        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/profiles/'.$slug)->assertForbidden();
    }
}
