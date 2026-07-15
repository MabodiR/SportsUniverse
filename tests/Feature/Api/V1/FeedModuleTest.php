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

    public function test_video_post_can_include_two_images_and_select_a_cover(): void
    {
        $user = User::factory()->create();
        $videoMedia = Media::factory()->for($user)->create(['kind' => 'video']);
        $images = Media::factory()->count(2)->for($user)->create(['kind' => 'image']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/videos', [
            'media_id' => $videoMedia->public_id,
            'image_media_ids' => $images->pluck('public_id')->all(),
            'cover_media_id' => $images->last()->public_id,
            'publish' => true,
        ]);

        $response->assertCreated()->assertJsonCount(2, 'data.images');
        $video = Video::firstOrFail();
        $this->assertDatabaseHas('video_images', ['video_id' => $video->id, 'media_id' => $images->last()->id, 'is_cover' => true]);
    }

    public function test_user_can_publish_picture_only_post_and_edit_metadata(): void
    {
        $user = User::factory()->create();
        $images = Media::factory()->count(2)->for($user)->create(['kind'=>'image']);
        $created = $this->actingAs($user,'sanctum')->postJson('/api/v1/videos',[
            'image_media_ids'=>$images->pluck('public_id')->all(),'cover_media_id'=>$images->first()->public_id,
            'caption'=>'Match day','hashtags'=>['Football'],'location_name'=>'Soweto','comments_enabled'=>false,'publish'=>true,
        ])->assertCreated()->assertJsonPath('data.type','images')->assertJsonPath('data.location.name','Soweto');
        $id=$created->json('data.id');
        $this->patchJson('/api/v1/videos/'.$id,['caption'=>'Final whistle','hashtags'=>['Champions'],'visibility'=>'followers'])
            ->assertOk()->assertJsonPath('data.caption','Final whistle')->assertJsonPath('data.visibility','followers');
        $this->assertDatabaseHas('videos',['public_id'=>$id,'media_id'=>null,'location_name'=>'Soweto','comments_enabled'=>false]);
    }

    public function test_owner_can_delete_post(): void
    {
        $user=User::factory()->create();
        $post=Video::factory()->for($user)->create();
        $this->actingAs($user,'sanctum')->deleteJson('/api/v1/videos/'.$post->public_id)->assertOk();
        $this->assertDatabaseMissing('videos',['id'=>$post->id]);
    }

    public function test_owner_can_list_and_publish_a_draft(): void
    {
        $user = User::factory()->create();
        $draft = Video::factory()->for($user)->create(['status' => 'draft', 'published_at' => null]);
        Video::factory()->for($user)->create(['status' => 'published']);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/videos/mine?status=draft')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $draft->public_id);
        $this->postJson('/api/v1/videos/'.$draft->public_id.'/publish')->assertOk()->assertJsonPath('data.status', 'published');
        $this->assertNotNull($draft->fresh()->published_at);
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

    public function test_saved_feed_only_contains_posts_saved_by_the_viewer(): void
    {
        $viewer = User::factory()->create();
        $saved = Video::factory()->create();
        Video::factory()->create();
        $saved->savers()->attach($viewer, ['created_at' => now()]);

        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/feed/saved')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $saved->public_id)->assertJsonPath('data.0.viewer.saved', true);
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

    public function test_user_can_like_and_unlike_a_comment(): void
    {
        $viewer = User::factory()->create();
        $video = Video::factory()->create();
        $comment = $this->actingAs($viewer, 'sanctum')->postJson('/api/v1/videos/'.$video->public_id.'/comments', ['body' => 'Great work!'])->assertCreated()->json('data');

        $this->postJson('/api/v1/comments/'.$comment['id'].'/like')->assertOk()->assertJsonPath('data.liked', true)->assertJsonPath('data.likes_count', 1);
        $this->postJson('/api/v1/comments/'.$comment['id'].'/like')->assertOk()->assertJsonPath('data.liked', false)->assertJsonPath('data.likes_count', 0);
    }
}
