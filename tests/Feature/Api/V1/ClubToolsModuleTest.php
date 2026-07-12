<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClubToolsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['athlete', 'club', 'scout', 'fan'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_club_can_create_a_workspace_shortlist_note_and_comparison(): void
    {
        $club = $this->member('club', 'Township United');
        $first = $this->member('athlete', 'First Athlete');
        $second = $this->member('athlete', 'Second Athlete');

        $this->actingAs($club, 'sanctum')->getJson('/api/v1/club-tools')->assertOk()->assertJsonPath('data.workspace.name', 'Township United');
        $list = $this->postJson('/api/v1/club-tools/shortlists', ['name' => 'Priority prospects'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/club-tools/shortlists/$list/athletes", ['athlete_id' => $first->id])->assertOk();
        $this->postJson('/api/v1/club-tools/notes', ['athlete_id' => $first->id, 'note' => 'Excellent awareness.', 'rating' => 9])->assertCreated();
        $this->postJson('/api/v1/club-tools/compare', ['athlete_ids' => [$first->id, $second->id]])->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_ineligible_account_cannot_create_a_club_workspace(): void
    {
        $this->actingAs($this->member('fan', 'Fan Account'), 'sanctum')->getJson('/api/v1/club-tools')->assertForbidden();
    }

    private function member(string $role, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole($role);
        $user->profile()->create(['slug' => str($name)->slug(), 'is_public' => true, 'completeness' => 80]);

        return $user;
    }
}
