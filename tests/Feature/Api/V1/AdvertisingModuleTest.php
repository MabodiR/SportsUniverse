<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Feed\Models\Video;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdvertisingModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['athlete', 'sponsor', 'admin'] as $role) Role::findOrCreate($role, 'web');
    }

    public function test_user_can_create_promotion_and_sponsorship_campaigns(): void
    {
        $user = $this->member('athlete');
        $video = Video::factory()->for($user)->create(['status' => 'published']);
        $base = ['title' => 'Winter Campaign', 'goal' => 'views', 'audience' => ['gender' => 'all', 'min_age' => 18, 'max_age' => 35], 'daily_budget_cents' => 10000, 'starts_on' => today()->toDateString(), 'ends_on' => today()->addDays(2)->toDateString()];

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/campaigns', [...$base, 'campaign_type' => 'post_promotion', 'video_id' => $video->public_id, 'submit' => true])
            ->assertCreated()->assertJsonPath('data.status', 'pending_review')->assertJsonPath('data.total_budget_cents', 30000);
        $this->postJson('/api/v1/campaigns', [...$base, 'campaign_type' => 'sponsorship', 'goal' => 'applications', 'video_id' => null, 'submit' => false])
            ->assertCreated()->assertJsonPath('data.status', 'draft');
        $this->getJson('/api/v1/campaigns')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_admin_can_activate_campaign_and_active_events_are_counted(): void
    {
        $owner = $this->member('sponsor');
        $admin = $this->member('admin');
        $created = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/campaigns', ['campaign_type' => 'sponsorship', 'title' => 'Talent Partner', 'goal' => 'awareness', 'daily_budget_cents' => 5000, 'starts_on' => today()->toDateString(), 'ends_on' => today()->addWeek()->toDateString(), 'submit' => true])->assertCreated();
        $id = $created->json('data.id');

        $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/admin/campaigns/'.$id.'/review', ['status' => 'active'])->assertOk();
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/campaigns/'.$id.'/events', ['event' => 'impression'])->assertOk();
        $this->postJson('/api/v1/campaigns/'.$id.'/events', ['event' => 'click'])->assertOk();
        $this->assertDatabaseHas('ad_campaigns', ['public_id' => $id, 'impressions_count' => 1, 'clicks_count' => 1]);
    }

    public function test_user_cannot_promote_another_users_post(): void
    {
        $owner = $this->member('athlete');
        $other = $this->member('athlete');
        $video = Video::factory()->for($other)->create(['status' => 'published']);

        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/campaigns', ['campaign_type' => 'post_promotion', 'video_id' => $video->public_id, 'title' => 'Invalid', 'goal' => 'views', 'daily_budget_cents' => 5000, 'starts_on' => today()->toDateString(), 'ends_on' => today()->addDay()->toDateString()])->assertUnprocessable();
    }

    private function member(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->profile()->create(['is_public' => true]);
        return $user;
    }
}
