<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Opportunities\Models\Opportunity;
use App\Domain\Sports\Models\Sport;
use App\Models\User;
use Database\Seeders\SportSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OpportunityModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['athlete', 'fan', 'club', 'academy', 'business', 'sponsor', 'admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }$this->seed(SportSeeder::class);
    }

    public function test_organisation_role_can_publish_opportunity(): void
    {
        $club = $this->member('club');
        $sport = Sport::where('slug', 'football')->firstOrFail();
        $response = $this->actingAs($club, 'sanctum')->postJson('/api/v1/opportunities', ['title' => 'Under 23 Trials', 'type' => 'trial', 'description' => 'Open football trials for emerging players.', 'sport_id' => $sport->id, 'country' => 'ZA', 'city' => 'Johannesburg', 'minimum_age' => 18, 'maximum_age' => 23, 'deadline' => now()->addWeeks(2)->toISOString(), 'publish' => true]);
        $response->assertCreated()->assertJsonPath('data.status', 'published')->assertJsonPath('data.title', 'Under 23 Trials');
    }

    public function test_fan_cannot_publish_opportunity(): void
    {
        $fan = $this->member('fan');
        $this->actingAs($fan, 'sanctum')->postJson('/api/v1/opportunities', ['title' => 'Invalid', 'type' => 'trial', 'description' => 'Not allowed'])->assertForbidden();
    }

    public function test_athlete_can_apply_once_and_counter_is_updated(): void
    {
        $club = $this->member('club');
        $athlete = $this->member('athlete', ['date_of_birth' => today()->subYears(20)]);
        $opportunity = Opportunity::factory()->for($club, 'poster')->create(['minimum_age' => 18, 'maximum_age' => 23]);
        $this->actingAs($athlete, 'sanctum')->postJson('/api/v1/opportunities/'.$opportunity->public_id.'/apply', ['cover_letter' => 'I am ready to trial.'])->assertCreated()->assertJsonPath('data.status', 'submitted');
        $this->postJson('/api/v1/opportunities/'.$opportunity->public_id.'/apply')->assertUnprocessable();
        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'applications_count' => 1]);
    }

    public function test_expired_opportunity_rejects_application(): void
    {
        $club = $this->member('club');
        $athlete = $this->member('athlete', ['date_of_birth' => today()->subYears(20)]);
        $opportunity = Opportunity::factory()->for($club, 'poster')->create(['deadline' => now()->subDay()]);
        $this->actingAs($athlete, 'sanctum')->postJson('/api/v1/opportunities/'.$opportunity->public_id.'/apply')->assertForbidden();
    }

    public function test_poster_can_review_application_and_applicant_is_notified(): void
    {
        $club = $this->member('club');
        $athlete = $this->member('athlete', ['date_of_birth' => today()->subYears(20)]);
        $opportunity = Opportunity::factory()->for($club, 'poster')->create();
        $application = $opportunity->applications()->create(['public_id' => (string) Str::ulid(), 'user_id' => $athlete->id, 'status' => 'submitted']);
        $this->actingAs($club, 'sanctum')->patchJson('/api/v1/applications/'.$application->public_id, ['status' => 'shortlisted', 'reviewer_notes' => 'Attend final assessment.'])->assertOk()->assertJsonPath('data.status', 'shortlisted');
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $athlete->id]);
    }

    public function test_user_can_save_and_filter_opportunities(): void
    {
        $viewer = $this->member('fan');
        $club = $this->member('club');
        $matching = Opportunity::factory()->for($club, 'poster')->create(['type' => 'sponsorship', 'city' => 'Pretoria']);
        Opportunity::factory()->for($club, 'poster')->create(['type' => 'job', 'city' => 'Cape Town']);
        $this->actingAs($viewer, 'sanctum')->postJson('/api/v1/opportunities/'.$matching->public_id.'/save')->assertOk();
        $this->getJson('/api/v1/opportunities?type=sponsorship&city=Pretoria')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.viewer.saved', true);
    }

    private function member(string $role, array $profile = []): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->profile()->create([...['slug' => fake()->unique()->slug(), 'is_public' => true], ...$profile]);

        return $user;
    }
}
