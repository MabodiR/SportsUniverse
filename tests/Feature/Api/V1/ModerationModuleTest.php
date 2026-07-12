<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Feed\Models\Video;
use App\Domain\Media\Models\Media;
use App\Domain\Moderation\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModerationModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['fan', 'athlete', 'admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_user_can_report_video(): void
    {
        $reporter = $this->member('fan');
        $video = Video::factory()->create();
        $this->actingAs($reporter, 'sanctum')->postJson('/api/v1/reports', ['type' => 'video', 'id' => $video->public_id, 'reason' => 'spam', 'details' => 'Repeated promotional content.'])->assertCreated()->assertJsonPath('data.status', 'open');
        $this->assertDatabaseHas('reports', ['reporter_id' => $reporter->id, 'reportable_id' => $video->id, 'reason' => 'spam']);
    }

    public function test_admin_can_moderate_media_with_audit_and_notification(): void
    {
        $admin = $this->member('admin');
        $owner = $this->member('athlete');
        $media = Media::factory()->for($owner)->create(['moderation_status' => 'pending']);
        $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/admin/moderation/media/'.$media->public_id, ['status' => 'rejected', 'notes' => 'Unsupported content'])->assertOk();
        $this->assertDatabaseHas('media', ['id' => $media->id, 'moderation_status' => 'rejected']);
        $this->assertDatabaseHas('moderation_actions', ['moderator_id' => $admin->id, 'action' => 'moderate_media', 'new_status' => 'rejected']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $owner->id]);
    }

    public function test_admin_can_resolve_report_and_verify_profile(): void
    {
        $admin = $this->member('admin');
        $athlete = $this->member('athlete');
        $report = Report::factory()->create();
        $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/admin/moderation/reports/'.$report->public_id, ['status' => 'resolved', 'action' => 'content_removed'])->assertOk();
        $this->patchJson('/api/v1/admin/users/'.$athlete->id.'/verification', ['status' => 'verified'])->assertOk();
        $this->assertNotNull($athlete->profile->fresh()->verified_at);
        $this->assertDatabaseHas('moderation_actions', ['moderator_id' => $admin->id, 'action' => 'verify_profile']);
    }

    public function test_non_admin_cannot_access_moderation_dashboard(): void
    {
        $fan = $this->member('fan');
        $this->actingAs($fan, 'sanctum')->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }

    public function test_filament_panel_rejects_non_admins(): void
    {
        $fan = $this->member('fan');
        $this->actingAs($fan, 'web')->get('/admin')->assertForbidden();
    }

    public function test_filament_panel_allows_admins(): void
    {
        $admin = $this->member('admin');
        $this->actingAs($admin, 'web')->get('/admin')->assertOk();
        foreach (['users', 'media', 'videos', 'opportunities', 'reports'] as $resource) {
            $this->get('/admin/'.$resource)->assertOk();
        }
    }

    private function member(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->profile()->create(['slug' => fake()->unique()->slug(), 'is_public' => true]);

        return $user;
    }
}
