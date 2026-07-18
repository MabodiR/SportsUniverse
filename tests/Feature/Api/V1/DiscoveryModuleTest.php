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

    public function test_unified_discovery_returns_categorized_results_and_trends(): void
    {
        $viewer=$this->member('Discovery Viewer','fan',['city'=>'Soweto']);
        $athlete=$this->member('Soweto Striker','athlete',['city'=>'Soweto','bio'=>'Fast football talent']);
        \App\Domain\Feed\Models\Video::factory()->for($athlete)->create(['caption'=>'Football training','hashtags'=>['football','training'],'location_name'=>'Orlando Stadium','published_at'=>now()]);
        $response=$this->actingAs($viewer,'sanctum')->getJson('/api/v1/search/all?q=football')->assertOk();
        $response->assertJsonPath('data.athletes.0.name','Soweto Striker')->assertJsonPath('data.videos.0.caption','Football training')->assertJsonPath('discovery.trending_hashtags.0.tag','football');
        $this->getJson('/api/v1/search/all?q='.urlencode('#training'))->assertOk()->assertJsonPath('data.videos.0.caption','Football training');
        $this->getJson('/api/v1/search/all?q='.urlencode('Orlando Stadium'))->assertOk()->assertJsonPath('data.videos.0.location','Orlando Stadium');
    }

    public function test_user_can_save_reuse_and_delete_searches(): void
    {
        $viewer=$this->member('Search Saver','fan');
        $saved=$this->actingAs($viewer,'sanctum')->postJson('/api/v1/saved-searches',['name'=>'Local football','query'=>'football','filters'=>['city'=>'Soweto']])->assertCreated();
        $this->getJson('/api/v1/saved-searches')->assertOk()->assertJsonPath('data.0.name','Local football')->assertJsonPath('data.0.filters.city','Soweto');
        $this->deleteJson('/api/v1/saved-searches/'.$saved->json('data.id'))->assertNoContent();
        $this->assertDatabaseCount('saved_searches',0);
    }

    public function test_user_can_save_list_and_remove_public_profiles(): void
    {
        $viewer = $this->member('Talent Saver', 'fan');
        $athlete = $this->member('Saved Athlete', 'athlete', ['city' => 'Pretoria']);

        $this->actingAs($viewer, 'sanctum')->postJson('/api/v1/saved-profiles/'.$athlete->id)
            ->assertCreated()->assertJsonPath('data.saved', true);
        $this->getJson('/api/v1/saved-profiles')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Saved Athlete');
        $this->deleteJson('/api/v1/saved-profiles/'.$athlete->id)
            ->assertOk()->assertJsonPath('data.saved', false);
        $this->assertDatabaseCount('saved_profiles', 0);
    }

    public function test_private_and_own_profiles_cannot_be_saved(): void
    {
        $viewer = $this->member('Talent Saver', 'fan');
        $private = $this->member('Private Athlete', 'athlete', ['is_public' => false]);

        $this->actingAs($viewer, 'sanctum')->postJson('/api/v1/saved-profiles/'.$private->id)->assertNotFound();
        $this->postJson('/api/v1/saved-profiles/'.$viewer->id)->assertUnprocessable();
    }

    public function test_women_in_sports_hub_returns_public_women_content_and_opportunities(): void
    {
        $viewer = $this->member('Hub Viewer', 'fan');
        $football = Sport::where('slug', 'football')->firstOrFail();
        $woman = $this->member('Naledi Champion', 'athlete', ['gender' => 'female', 'city' => 'Pretoria']);
        $woman->athleteProfile()->create(['sport_id' => $football->id]);
        $man = $this->member('Male Athlete', 'athlete', ['gender' => 'male']);
        $man->athleteProfile()->create(['sport_id' => $football->id]);
        \App\Domain\Feed\Models\Video::factory()->for($woman)->create(['sport_id' => $football->id, 'caption' => 'Women football highlight', 'status' => 'published', 'visibility' => 'public', 'published_at' => now()]);
        Opportunity::factory()->for($viewer, 'poster')->create(['sport_id' => $football->id, 'title' => 'Women Football Scholarship', 'description' => 'A pathway for emerging women players.', 'status' => 'published', 'deadline' => now()->addMonth()]);

        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/women-in-sports?sport_id='.$football->id)
            ->assertOk()->assertJsonPath('data.profiles.0.name', 'Naledi Champion')
            ->assertJsonCount(1, 'data.profiles')->assertJsonPath('data.videos.0.caption', 'Women football highlight')
            ->assertJsonPath('data.opportunities.0.title', 'Women Football Scholarship');
    }

    private function member(string $name, string $role, array $profile = []): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole($role);
        $user->profile()->create([...['slug' => str($name)->slug()->value(), 'is_public' => true, 'completeness' => 70], ...$profile]);

        return $user;
    }
}
