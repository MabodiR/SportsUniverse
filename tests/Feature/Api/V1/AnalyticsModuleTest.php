<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Analytics\Jobs\AggregateDailyMetrics;
use App\Domain\Feed\Models\Video;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnalyticsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['athlete', 'fan', 'admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_profile_view_is_counted_once_per_viewer_per_day(): void
    {
        $athlete = $this->member('athlete', 'tracked-athlete');
        $viewer = $this->member('fan', 'viewer');
        $this->actingAs($viewer, 'sanctum')->postJson('/api/v1/profiles/tracked-athlete/views')->assertOk()->assertJsonPath('data.counted', true);
        $this->postJson('/api/v1/profiles/tracked-athlete/views')->assertJsonPath('data.counted', false);
        $this->assertDatabaseCount('profile_views', 1);
        $this->assertSame(1, $athlete->profile->fresh()->views_count);
    }

    public function test_self_profile_view_is_not_counted(): void
    {
        $athlete = $this->member('athlete', 'self-view');
        $this->actingAs($athlete, 'sanctum')->postJson('/api/v1/profiles/self-view/views')->assertJsonPath('data.counted', false);
        $this->assertDatabaseCount('profile_views', 0);
    }

    public function test_daily_aggregation_is_idempotent_and_creator_dashboard_returns_totals(): void
    {
        $athlete = $this->member('athlete', 'analytics-athlete');
        $viewer = $this->member('fan', 'analytics-viewer');
        $viewer->profile()->update(['city' => 'Soweto']);
        $video = Video::factory()->for($athlete)->create(['views_count' => 4, 'likes_count' => 2, 'comments_count' => 1, 'shares_count' => 1]);
        DB::table('video_views')->insert(['video_id' => $video->id, 'user_id' => $viewer->id, 'watched_ms' => 1000, 'completed' => true, 'viewed_on' => today()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('video_likes')->insert(['video_id' => $video->id, 'user_id' => $viewer->id, 'created_at' => now()]);
        $job = new AggregateDailyMetrics(today()->toDateString());
        $job->handle();
        $job->handle();
        $this->assertDatabaseCount('analytics_daily_metrics', 10);
        $this->assertDatabaseHas('analytics_daily_metrics', ['dimension_type' => 'user', 'dimension_id' => $athlete->id, 'metric' => 'video_views', 'value' => 1]);
        DB::table('profile_views')->insert(['profile_user_id' => $athlete->id, 'viewer_id' => $viewer->id, 'source' => 'profile', 'viewed_on' => today(), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($athlete, 'sanctum')->getJson('/api/v1/analytics/me?days=30')->assertOk()->assertJsonPath('data.totals.video_views', 4)->assertJsonPath('data.totals.likes', 2)->assertJsonPath('data.period.views', 2)->assertJsonPath('data.period.engagement_rate', 100)->assertJsonPath('data.locations.0.city', 'Soweto')->assertJsonPath('data.top_videos.0.id', $video->public_id);
    }

    public function test_admin_analytics_is_protected(): void
    {
        $fan = $this->member('fan', 'regular-fan');
        $admin = $this->member('admin', 'analytics-admin');
        $this->actingAs($fan, 'sanctum')->getJson('/api/v1/admin/analytics')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/analytics?days=7')->assertOk()->assertJsonPath('data.period_days', 7);
    }

    private function member(string $role, string $slug): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->profile()->create(['slug' => $slug, 'is_public' => true]);

        return $user;
    }
}
