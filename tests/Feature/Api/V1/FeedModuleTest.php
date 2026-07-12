<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Feed\Models\Video;
use App\Domain\Media\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_publish_processed_approved_video(): void
    {
        $user = User::factory()->create();
        $media = Media::factory()->for($user)->create(['kind' => 'video']);
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/videos', ['media_id' => $media->public_id, 'caption' => 'Match winner #football', 'hashtags' => ['#Football', 'Talent'], 'publish' => true]);
        $response->assertCreated()->assertJsonPath('data.status', 'published')->assertJsonPath('data.hashtags.0', 'football');
        $this->assertDatabaseHas('videos', ['user_id' => $user->id, 'media_id' => $media->id, 'status' => 'published']);
    }

    public function test_unprocessed_media_cannot_be_published(): void
    {
        $user = User::factory()->create();
        $media = Media::factory()->for($user)->create(['kind' => 'video', 'processing_status' => 'pending']);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/videos', ['media_id' => $media->public_id, 'publish' => true])->assertUnprocessable()->assertJsonValidationErrors('media_id');
    }

    public function test_following_feed_only_contains_followed_creators(): void
    {
        $viewer = User::factory()->create();
        $followed = User::factory()->create();
        $other = User::factory()->create();
        $viewer->following()->attach($followed);
        $included = Video::factory()->for($followed)->create();
        Video::factory()->for($other)->create();
        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/feed/following')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $included->public_id);
    }

    public function test_follow_and_unfollow_return_both_updated_counts(): void
    {
        $viewer = User::factory()->create();
        $athlete = User::factory()->create();

        $this->actingAs($viewer, 'sanctum')->postJson('/api/v1/profiles/'.$athlete->id.'/follow')
            ->assertOk()
            ->assertJsonPath('data.following', true)
            ->assertJsonPath('data.followers_count', 1)
            ->assertJsonPath('data.viewer_following_count', 1);

        $this->postJson('/api/v1/profiles/'.$athlete->id.'/follow')
            ->assertOk()
            ->assertJsonPath('data.created', false)
            ->assertJsonPath('data.followers_count', 1);

        $this->deleteJson('/api/v1/profiles/'.$athlete->id.'/follow')
            ->assertOk()
            ->assertJsonPath('data.followers_count', 0)
            ->assertJsonPath('data.viewer_following_count', 0);
    }

    public function test_like_and_save_are_idempotent_toggles(): void
    {
        $viewer = User::factory()->create();
        $video = Video::factory()->create();
        $this->actingAs($viewer, 'sanctum')->postJson('/api/v1/videos/'.$video->public_id.'/like')->assertOk()->assertJsonPath('data.likes_count', 1);
        $this->postJson('/api/v1/videos/'.$video->public_id.'/like')->assertOk()->assertJsonPath('data.likes_count', 0);
        $this->postJson('/api/v1/videos/'.$video->public_id.'/save')->assertOk()->assertJsonPath('data.saved', true);
        $this->assertDatabaseCount('saved_videos', 1);
    }

    public function test_view_is_counted_once_per_user_per_day(): void
    {
        $viewer = User::factory()->create();
        $video = Video::factory()->create();
        $this->actingAs($viewer, 'sanctum')->postJson('/api/v1/videos/'.$video->public_id.'/views', ['watched_ms' => 1000])->assertJsonPath('data.counted', true);
        $this->postJson('/api/v1/videos/'.$video->public_id.'/views', ['watched_ms' => 3000, 'completed' => true])->assertJsonPath('data.counted', false)->assertJsonPath('data.views_count', 1);
        $this->assertDatabaseHas('video_views', ['video_id' => $video->id, 'watched_ms' => 3000, 'completed' => true]);
    }

    public function test_user_can_comment_and_reply_on_video(): void
    {
        $viewer = User::factory()->create();
        $video = Video::factory()->create();
        $first = $this->actingAs($viewer, 'sanctum')->postJson('/api/v1/videos/'.$video->public_id.'/comments', ['body' => 'Great finish!'])->assertCreated();
        $this->postJson('/api/v1/videos/'.$video->public_id.'/comments', ['body' => 'Agreed', 'parent_id' => $first->json('data.id')])->assertCreated()->assertJsonPath('data.parent_id', $first->json('data.id'));
        $this->assertDatabaseHas('videos',['id' => $video->id, 'comments_count' => 2]);
    }
}
