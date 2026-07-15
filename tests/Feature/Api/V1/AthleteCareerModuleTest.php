<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Profiles\Actions\EnsureProfileSlug;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AthleteCareerModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('athlete', 'web');
        Role::findOrCreate('fan', 'web');
    }

    public function test_athlete_can_manage_complete_career_record(): void
    {
        $athlete = $this->member('athlete');
        $this->actingAs($athlete, 'sanctum');

        $history = $this->postJson('/api/v1/profile/career/history', [
            'team_name' => 'Pretoria United', 'role' => 'Striker', 'level' => 'Provincial',
            'started_on' => '2024-01-01', 'is_current' => true, 'description' => 'First team player.',
        ])->assertCreated()->json('data.id');
        $achievement = $this->postJson('/api/v1/profile/career/achievements', [
            'title' => 'Golden Boot', 'issuer' => 'Gauteng League', 'achieved_on' => '2025-06-01',
        ])->assertCreated()->json('data.id');
        $statistic = $this->postJson('/api/v1/profile/career/statistics', [
            'season' => '2025/26', 'competition' => 'Gauteng League', 'name' => 'Goals', 'value' => 18, 'unit' => 'goals',
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/v1/profile/career')->assertOk()
            ->assertJsonPath('data.history.0.team_name', 'Pretoria United')
            ->assertJsonPath('data.achievements.0.title', 'Golden Boot')
            ->assertJsonPath('data.statistics.0.name', 'Goals');

        $this->deleteJson('/api/v1/profile/career/history/'.$history)->assertOk();
        $this->deleteJson('/api/v1/profile/career/achievements/'.$achievement)->assertOk();
        $this->deleteJson('/api/v1/profile/career/statistics/'.$statistic)->assertOk();
        $this->assertDatabaseCount('athlete_career_entries', 0);
        $this->assertDatabaseCount('athlete_achievements', 0);
        $this->assertDatabaseCount('athlete_statistics', 0);
    }

    public function test_non_athlete_cannot_manage_career_records(): void
    {
        $fan = $this->member('fan');
        $this->actingAs($fan, 'sanctum')->getJson('/api/v1/profile/career')->assertForbidden();
        $this->postJson('/api/v1/profile/career/achievements', ['title' => 'Not allowed'])->assertForbidden();
    }

    public function test_public_athlete_page_contains_career_data(): void
    {
        $athlete = $this->member('athlete');
        $slug = app(EnsureProfileSlug::class)->execute($athlete->load('profile'));
        $athlete->careerEntries()->create(['team_name' => 'Universe FC', 'is_current' => true]);
        $athlete->achievements()->create(['title' => 'Player of the Year']);
        $athlete->athleteStatistics()->create(['season' => '2026', 'name' => 'Assists', 'value' => 11, 'unit' => 'assists']);

        $this->get('/@'.$slug)->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('athlete.career_history.0.team_name', 'Universe FC')
                ->where('athlete.achievements.0.title', 'Player of the Year')
                ->where('athlete.statistics.0.name', 'Assists'));

        $this->getJson('/api/v1/profiles/'.$slug)->assertOk()
            ->assertJsonPath('data.career.history.0.team_name', 'Universe FC')
            ->assertJsonPath('data.career.achievements.0.title', 'Player of the Year')
            ->assertJsonPath('data.career.statistics.0.name', 'Assists');
    }

    private function member(string $role): User
    {
        $user = User::factory()->create();
        $user->profile()->create(['is_public' => true]);
        $user->assignRole($role);

        return $user;
    }
}
