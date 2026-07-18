<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Profiles\Actions\EnsureProfileSlug;
use App\Domain\Sports\Models\Sport;
use App\Models\User;
use Database\Seeders\SportSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProfileModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['athlete', 'fan', 'coach', 'referee', 'linesman', 'scout', 'agent', 'club', 'academy', 'business', 'sponsor', 'admin'] as $role) {
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

    public function test_owner_can_upload_a_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->profile()->create();

        $response = $this->actingAs($user, 'sanctum')->post('/api/v1/profile/photo', [
            'photo' => UploadedFile::fake()->image('profile.jpg', 600, 600),
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('data.url', fn ($url) => str_starts_with($url, '/storage/profiles/'.$user->id.'/'));
        Storage::disk('public')->assertExists(str($response->json('data.url'))->after('/storage/')->value());
    }

    public function test_owner_can_upload_a_profile_cover(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->profile()->create();

        $response = $this->actingAs($user, 'sanctum')->post('/api/v1/profile/cover', [
            'cover' => UploadedFile::fake()->image('banner.jpg', 1200, 450),
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('data.url', fn ($url) => str_starts_with($url, '/storage/profiles/'.$user->id.'/'));
        $this->assertSame($response->json('data.url'), $user->profile->fresh()->cover_image_path);
        Storage::disk('public')->assertExists(str($response->json('data.url'))->after('/storage/')->value());
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

    public function test_professional_can_update_role_specific_profile(): void
    {
        $user = User::factory()->create();
        $user->profile()->create();
        $user->assignRole('referee');

        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/profile/professional', [
            'professional_type' => 'referee',
            'specialisation' => 'Football match officiating',
            'years_experience' => 7,
            'certifications' => ['SAFA Referee Level 2'],
            'is_available' => true,
        ])->assertOk()
            ->assertJsonPath('data.professional.professional_type', 'referee')
            ->assertJsonPath('data.professional.certifications.0', 'SAFA Referee Level 2')
            ->assertJsonPath('data.is_available', true);

        $this->assertDatabaseHas('professional_profiles', ['user_id' => $user->id, 'years_experience' => 7]);
    }

    public function test_organisation_can_update_identity_contacts_and_services(): void
    {
        $user = User::factory()->create();
        $user->profile()->create();
        $user->assignRole('club');

        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/profile/organisation', [
            'organisation_name' => 'Pretoria United',
            'organisation_type' => 'club',
            'registration_number' => 'NPC-2026-42',
            'website' => 'https://pretoria-united.test',
            'contact_email' => 'hello@pretoria-united.test',
            'contact_phone' => '+27 12 555 0100',
            'services' => ['Youth development', 'Trials'],
        ])->assertOk()
            ->assertJsonPath('data.organisation.registration_number', 'NPC-2026-42')
            ->assertJsonPath('data.organisation.services.1', 'Trials');

        $this->assertDatabaseHas('organisation_profiles', ['user_id' => $user->id, 'organisation_name' => 'Pretoria United']);
    }

    public function test_user_cannot_update_a_profile_type_outside_their_role(): void
    {
        $user = User::factory()->create();
        $user->profile()->create();
        $user->assignRole('athlete');

        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/profile/professional', [
            'professional_type' => 'coach',
            'is_available' => true,
        ])->assertForbidden();
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
