<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Discovery\Support\ProfileDocument;
use App\Domain\Opportunities\Models\Opportunity;
use App\Domain\Sports\Models\Sport;
use App\Models\User;
use Database\Seeders\SportSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DiscoveryModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['discovery.driver' => 'database']);
        foreach (['athlete', 'coach', 'fan'] as $role) {
            Role::findOrCreate($role, 'web');
        }$this->seed(SportSeeder::class);
    }

    public function test_search_finds_public_profile_by_name_and_logs_query(): void
    {
        $viewer = $this->member('Search Viewer', 'fan');
        $athlete = $this->member('Lerato Lightning', 'athlete', ['city' => 'Johannesburg', 'completeness' => 90]);
        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/search/profiles?q=Lerato')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', $athlete->name)->assertJsonPath('meta.engine', 'database');
        $this->assertDatabaseHas('search_logs', ['user_id' => $viewer->id, 'query' => 'Lerato', 'results_count' => 1]);
    }

    public function test_filters_by_sport_position_location_age_and_availability(): void
    {
        $viewer = $this->member('Viewer', 'fan');
        $sport = Sport::where('slug', 'football')->firstOrFail();
        $position = $sport->positions()->where('slug', 'striker')->firstOrFail();
        $athlete = $this->member('Available Striker', 'athlete', ['country' => 'ZA', 'province' => 'Gauteng', 'city' => 'Pretoria', 'date_of_birth' => today()->subYears(21), 'is_available' => true, 'completeness' => 95]);
        $athlete->athleteProfile()->create(['sport_id' => $sport->id, 'position_id' => $position->id, 'club_name' => 'Universe FC']);
        $url = '/api/v1/search/profiles?role=athlete&sport_id='.$sport->id.'&position_id='.$position->id.'&country=ZA&city=Pretoria&min_age=18&max_age=25&available=1&min_completeness=80';
        $this->actingAs($viewer, 'sanctum')->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Available Striker');
    }

    public function test_private_profiles_are_never_returned(): void
    {
        $viewer = $this->member('Viewer', 'fan');
        $this->member('Hidden Talent', 'athlete', ['is_public' => false]);
        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/search/profiles?q=Hidden')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_search_finds_profiles_by_location_age_and_opportunity_content(): void
    {
        $viewer = $this->member('Viewer', 'fan');
        $athlete = $this->member('Searchable Athlete', 'athlete', [
            'date_of_birth' => today()->subYears(21)->subMonth(),
            'city' => 'Pretoria',
            'locality' => 'Mamelodi',
        ]);
        Opportunity::factory()->for($athlete, 'poster')->create([
            'title' => 'Elite Goalkeeper Trial',
            'description' => 'A development opportunity for emerging keepers.',
            'status' => 'published',
        ]);

        foreach (['Pretoria', 'Mamelodi', '21', 'Goalkeeper', 'development opportunity'] as $term) {
            $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/search/profiles?q='.urlencode($term))
                ->assertOk()
                ->assertJsonPath('data.0.name', $athlete->name);
        }
    }

    public function test_profile_document_contains_rankable_taxonomy_fields(): void
    {
        $sport = Sport::where('slug', 'football')->firstOrFail();
        $athlete = $this->member('Indexed Athlete', 'athlete', ['city' => 'Soweto', 'completeness' => 88]);
        $athlete->athleteProfile()->create(['sport_id' => $sport->id, 'position_id' => $sport->positions()->first()->id, 'club_name' => 'Local Stars']);
        $document = app(ProfileDocument::class)->make($athlete);
        $this->assertSame('Football', $document['sport']);
        $this->assertSame('Local Stars', $document['club']);
        $this->assertSame(88, $document['completeness']);
    }

    private function member(string $name, string $role, array $profile = []): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole($role);
        $user->profile()->create([...['slug' => str($name)->slug()->value(), 'is_public' => true, 'completeness' => 70], ...$profile]);

        return $user;
    }
}
