<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Feed\Jobs\FinalizeQueuedPost;
use App\Domain\Feed\Jobs\AnalyzeVideoContent;
use App\Domain\Feed\Models\FeedSetting;
use App\Domain\Feed\Models\Video;
use App\Domain\Feed\Services\RecommendationFeed;
use App\Domain\Media\Models\Media;
use App\Domain\Notifications\Notifications\SportUniverseNotification;
use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FeedModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_you_feed_uses_precomputed_recommendation_order_when_available(): void
    {
        $viewer = User::factory()->create();
        $first = Video::factory()->create(['likes_count' => 0]);
        $second = Video::factory()->create(['likes_count' => 999]);
        $generation = (string) \Illuminate\Support\Str::ulid();
        DB::table('recommendation_feed_items')->insert([
            ['user_id' => $viewer->id, 'video_id' => $first->id, 'score' => 10, 'position' => 0, 'generation' => $generation, 'generated_at' => now()],
            ['user_id' => $viewer->id, 'video_id' => $second->id, 'score' => 9, 'position' => 1, 'generation' => $generation, 'generated_at' => now()],
        ]);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/feed/for-you')->assertOk();
        $response->assertJsonPath('data.0.id', $first->public_id)->assertJsonPath('data.1.id', $second->public_id);
    }

    public function test_for_you_feed_uses_cursor_pagination_for_scrolling(): void
    {
        $viewer = User::factory()->create();
        FeedSetting::current()->update(['page_size' => 2]);
        Video::factory()->count(5)->create();

        $first = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/feed/for-you')->assertOk()->assertJsonCount(2, 'data');
        $cursor = $first->json('meta.next_cursor');
        $this->assertNotEmpty($cursor);
        $firstIds = collect($first->json('data'))->pluck('id');

        $second = $this->getJson('/api/v1/feed/for-you?cursor='.urlencode($cursor))->assertOk()->assertJsonCount(2, 'data');
        $this->assertTrue($firstIds->intersect(collect($second->json('data'))->pluck('id'))->isEmpty());
    }

    public function test_for_you_feed_prioritizes_posts_not_viewed_recently(): void
    {
        $viewer = User::factory()->create();
        $viewed = Video::factory()->create(['likes_count' => 999]);
        $unseen = Video::factory()->create(['likes_count' => 0]);
        DB::table('video_views')->insert([
            'video_id' => $viewed->id,
            'user_id' => $viewer->id,
            'watched_ms' => 3000,
            'completed' => false,
            'viewed_on' => today()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/feed/for-you')
            ->assertOk()
            ->assertJsonPath('data.0.id', $unseen->public_id)
            ->assertJsonPath('data.1.id', $viewed->public_id);
    }

    public function test_for_you_feed_allows_an_older_viewed_post_to_rank_normally_again(): void
    {
        $viewer = User::factory()->create();
        $popular = Video::factory()->create(['likes_count' => 999]);
        $newer = Video::factory()->create(['likes_count' => 0]);
        DB::table('video_views')->insert([
            'video_id' => $popular->id,
            'user_id' => $viewer->id,
            'watched_ms' => 3000,
            'completed' => true,
            'viewed_on' => today()->subDays(4)->toDateString(),
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ]);

        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/feed/for-you')
            ->assertOk()
            ->assertJsonPath('data.0.id', $popular->public_id)
            ->assertJsonPath('data.1.id', $newer->public_id);
    }

    public function test_content_analysis_creates_detailed_topics_and_an_embedding(): void
    {
        $video = Video::factory()->create(['country_code' => 'GB', 'league' => 'Premier League', 'team' => 'Arsenal', 'content_type' => 'match_highlight', 'skill_tags' => ['finishing']]);

        AnalyzeVideoContent::dispatchSync($video->id);

        $this->assertDatabaseHas('video_content_topics', ['video_id' => $video->id, 'dimension' => 'country', 'value' => 'gb']);
        $this->assertDatabaseHas('video_content_topics', ['video_id' => $video->id, 'dimension' => 'league', 'value' => 'premier league']);
        $this->assertCount(64, $video->fresh()->content_embedding);
        $this->assertNotNull($video->fresh()->analyzed_at);
    }

    public function test_positive_behavior_teaches_the_feed_detailed_content_preferences(): void
    {
        $viewer = User::factory()->create();
        $english = Video::factory()->create(['country_code' => 'GB', 'league' => 'Premier League', 'likes_count' => 0]);
        $southAfrican = Video::factory()->create(['country_code' => 'ZA', 'league' => 'PSL', 'likes_count' => 0]);
        AnalyzeVideoContent::dispatchSync($english->id);
        AnalyzeVideoContent::dispatchSync($southAfrican->id);

        $this->actingAs($viewer, 'sanctum')->postJson('/api/v1/videos/'.$english->public_id.'/like')->assertOk();

        $this->assertDatabaseHas('user_content_preferences', ['user_id' => $viewer->id, 'dimension' => 'league', 'value' => 'premier league']);
        $this->getJson('/api/v1/feed/for-you')->assertOk()->assertJsonPath('data.0.id', $english->public_id);
    }

    public function test_related_endpoint_uses_content_embeddings(): void
    {
        $viewer = User::factory()->create();
        $source = Video::factory()->create(['caption' => 'Arsenal Premier League finishing highlight', 'country_code' => 'GB', 'league' => 'Premier League', 'team' => 'Arsenal']);
        $related = Video::factory()->create(['caption' => 'Arsenal Premier League finishing highlight', 'country_code' => 'GB', 'league' => 'Premier League', 'team' => 'Arsenal']);
        Video::factory()->create(['caption' => 'Sundowns PSL goalkeeper training', 'country_code' => 'ZA', 'league' => 'PSL', 'team' => 'Sundowns']);
        AnalyzeVideoContent::dispatchSync($source->id);
        AnalyzeVideoContent::dispatchSync($related->id);
        Video::whereKeyNot($source->id)->whereNull('analyzed_at')->pluck('id')->each(fn ($id) => AnalyzeVideoContent::dispatchSync($id));

        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/videos/'.$source->public_id.'/related')
            ->assertOk()->assertJsonPath('data.0.id', $related->public_id);
    }

    public function test_recommendation_ids_recover_from_an_incomplete_cached_class(): void
    {
        $viewer = User::factory()->create();
        $video = Video::factory()->create();
        DB::table('recommendation_feed_items')->insert([
            'user_id' => $viewer->id,
            'video_id' => $video->id,
            'score' => 10,
            'position' => 0,
            'generation' => (string) \Illuminate\Support\Str::ulid(),
            'generated_at' => now(),
        ]);
        $version = FeedSetting::current()->updated_at?->getTimestamp() ?? 0;
        $key = "feed:recommended:{$version}:{$viewer->id}";
        Cache::put($key, new \__PHP_Incomplete_Class(), 60);

        $ids = app(RecommendationFeed::class)->ids($viewer);

        $this->assertSame([$video->id], $ids->all());
        $this->assertIsArray(Cache::get($key));
    }

    public function test_user_can_publish_processed_approved_video(): void
    {
        $user = User::factory()->create();
        $media = Media::factory()->for($user)->create(['kind' => 'video']);
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/videos', ['media_id' => $media->public_id, 'caption' => 'Match winner #football', 'hashtags' => ['#Football', 'Talent'], 'publish' => true]);
        $response->assertCreated()->assertJsonPath('data.status', 'published')->assertJsonPath('data.hashtags.0', 'football');
        $this->assertDatabaseHas('videos', ['user_id' => $user->id, 'media_id' => $media->id, 'status' => 'published']);
    }

    public function test_video_post_accepts_an_explicit_empty_image_list(): void
    {
        $user = User::factory()->create();
        $media = Media::factory()->for($user)->create(['kind' => 'video']);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/videos', [
            'media_id' => $media->public_id,
            'image_media_ids' => [],
            'publish' => true,
        ])->assertCreated()->assertJsonPath('data.type', 'video');
    }

    public function test_unprocessed_media_is_accepted_and_published_by_the_queue_when_ready(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $media = Media::factory()->for($user)->create(['kind' => 'video', 'processing_status' => 'pending']);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/videos', ['media_id' => $media->public_id, 'publish' => true])
            ->assertAccepted()
            ->assertJsonPath('queued', true)
            ->assertJsonPath('data.status', 'draft');

        $video = Video::firstOrFail();
        Queue::assertPushedOn('media', FinalizeQueuedPost::class, fn ($job) => $job->video->is($video) && $job->publishWhenReady);

        $media->update(['processing_status' => 'ready', 'moderation_status' => 'approved']);
        Notification::fake();
        (new FinalizeQueuedPost($video, true))->handle(app(NotificationDispatcher::class));

        $this->assertSame('published', $video->fresh()->status);
        $this->assertNotNull($video->fresh()->published_at);
        Notification::assertSentTo($user, SportUniverseNotification::class, fn ($notification) => $notification->payload['event'] === 'post_upload_completed');
    }

    public function test_media_cannot_be_published_as_multiple_posts(): void
    {
        $user = User::factory()->create();
        $video = Media::factory()->for($user)->create(['kind' => 'video']);
        $image = Media::factory()->for($user)->create(['kind' => 'image']);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/videos', [
            'media_id' => $video->public_id,
            'image_media_ids' => [$image->public_id],
            'publish' => true,
        ])->assertCreated();

        $this->postJson('/api/v1/videos', ['media_id' => $video->public_id, 'publish' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('media_id');
        $this->postJson('/api/v1/videos', ['image_media_ids' => [$image->public_id], 'publish' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image_media_ids');
        $this->assertDatabaseCount('videos', 1);
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

    public function test_following_feed_contains_followed_creators_and_the_viewers_own_posts(): void
    {
        $viewer = User::factory()->create();
        $followed = User::factory()->create();
        $other = User::factory()->create();
        $viewer->following()->attach($followed);
        $included = Video::factory()->for($followed)->create();
        $ownPost = Video::factory()->for($viewer)->create();
        Video::factory()->for($other)->create();
        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/feed/following')->assertOk()->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing([$included->public_id, $ownPost->public_id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_active_stories_are_follower_only_and_expire_after_24_hours(): void
    {
        $creator = User::factory()->create();
        $follower = User::factory()->create();
        $stranger = User::factory()->create();
        $follower->following()->attach($creator);
        $active = Video::factory()->for($creator)->create(['post_type' => 'story', 'visibility' => 'followers', 'expires_at' => now()->addHour()]);
        Video::factory()->for($creator)->create(['post_type' => 'story', 'visibility' => 'followers', 'expires_at' => now()->subSecond()]);

        $this->actingAs($follower, 'sanctum')->getJson('/api/v1/feed/following')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $active->public_id)
            ->assertJsonPath('data.0.is_story', true);
        $this->getJson('/api/v1/feed/stories')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $active->public_id);
        $this->actingAs($stranger, 'sanctum')->getJson('/api/v1/videos/'.$active->public_id)->assertForbidden();
        $this->actingAs($follower, 'sanctum')->getJson('/api/v1/videos/'.$active->public_id)->assertOk();
    }

    public function test_stories_do_not_appear_organically_in_for_you(): void
    {
        $viewer = User::factory()->create();
        Video::factory()->create(['post_type' => 'story', 'visibility' => 'followers', 'expires_at' => now()->addDay()]);

        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/feed/for-you')->assertOk()->assertJsonCount(0, 'data');
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

    public function test_repost_is_an_idempotent_toggle_and_appears_on_profile(): void
    {
        $viewer = User::factory()->create();
        $video = Video::factory()->create(['shares_count' => 0]);

        $this->actingAs($viewer, 'sanctum')->postJson('/api/v1/videos/'.$video->public_id.'/share', ['channel' => 'repost'])
            ->assertOk()->assertJsonPath('data.reposted', true)->assertJsonPath('data.shares_count', 1);
        $this->getJson('/api/v1/videos/mine/reposts')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $video->public_id)->assertJsonPath('data.0.viewer.reposted', true);

        $this->postJson('/api/v1/videos/'.$video->public_id.'/share', ['channel' => 'repost'])
            ->assertOk()->assertJsonPath('data.reposted', false)->assertJsonPath('data.shares_count', 0);
        $this->getJson('/api/v1/videos/mine/reposts')->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseMissing('video_shares', ['video_id' => $video->id, 'user_id' => $viewer->id, 'channel' => 'repost']);
    }

    public function test_not_interested_preference_is_recorded_and_filters_future_feeds(): void
    {
        $viewer = User::factory()->create();
        $creator = User::factory()->create();
        $hidden = Video::factory()->for($creator)->create();
        $alsoHidden = Video::factory()->for($creator)->create();
        $visible = Video::factory()->create();

        $this->actingAs($viewer, 'sanctum')->postJson('/api/v1/videos/'.$hidden->public_id.'/not-interested', [
            'scope' => 'creator', 'reason' => 'irrelevant', 'details' => 'Not relevant to my feed.',
        ])->assertCreated()->assertJsonPath('data.scope', 'creator');

        $this->assertDatabaseHas('feed_preferences', ['user_id' => $viewer->id, 'video_id' => $hidden->id, 'creator_id' => $creator->id, 'scope' => 'creator', 'reason' => 'irrelevant']);
        $response = $this->getJson('/api/v1/feed/for-you')->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($hidden->public_id));
        $this->assertFalse($ids->contains($alsoHidden->public_id));
        $this->assertTrue($ids->contains($visible->public_id));
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
