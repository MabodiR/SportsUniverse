<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_count_and_read_notifications(): void
    {
        $user = User::factory()->create();
        app(NotificationDispatcher::class)->send($user, 'followers', ['event' => 'new_follower', 'actor_id' => 99, 'actor_name' => 'New Fan']);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.category', 'followers');
        $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 1);
        $id = $user->notifications()->first()->id;
        $this->patchJson('/api/v1/notifications/'.$id.'/read')->assertOk()->assertJsonPath('data.id', $id);
        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread_count', 0);
    }

    public function test_user_can_mark_all_read_and_delete_notification(): void
    {
        $user = User::factory()->create();
        $dispatcher = app(NotificationDispatcher::class);
        $dispatcher->send($user, 'engagement', ['event' => 'video_liked']);
        $dispatcher->send($user, 'messages', ['event' => 'new_message']);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('data.updated', 2);
        $id = $user->notifications()->first()->id;
        $this->deleteJson('/api/v1/notifications/'.$id)->assertOk();
        $this->assertDatabaseMissing('notifications', ['id' => $id]);
    }

    public function test_disabled_preference_suppresses_category(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/notification-preferences', ['engagement' => false])->assertOk()->assertJsonPath('data.engagement', false);
        app(NotificationDispatcher::class)->send($user, 'engagement', ['event' => 'video_liked']);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_follow_creates_realtime_capable_notification(): void
    {
        $follower = User::factory()->create();
        $target = User::factory()->create();
        $this->actingAs($follower, 'sanctum')->postJson('/api/v1/profiles/'.$target->id.'/follow')->assertOk();
        $notification = $target->notifications()->firstOrFail();
        $this->assertSame('followers', $notification->data['category']);
        $this->assertSame('new_follower', $notification->data['event']);
    }

    public function test_user_cannot_modify_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        app(NotificationDispatcher::class)->send($owner, 'followers', ['event' => 'new_follower']);
        $id = $owner->notifications()->first()->id;
        $this->actingAs($outsider,'sanctum')->patchJson('/api/v1/notifications/'.$id.'/read')->assertNotFound();
    }
}
