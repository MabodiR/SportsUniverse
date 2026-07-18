<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Media\Jobs\ProcessMedia;
use App\Domain\Media\Models\Media;
use App\Domain\Feed\Models\Video;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['media.disk' => 'local']);
        Storage::fake('local');
        Queue::fake();
    }

    public function test_user_can_upload_image_and_processing_is_queued(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/media', ['kind' => 'image', 'collection' => 'gallery', 'file' => UploadedFile::fake()->image('goal.jpg', 1200, 800)]);
        $response->assertAccepted()->assertJsonPath('data.processing_status', 'pending')->assertJsonPath('data.moderation_status', 'approved');
        $media = Media::firstOrFail();
        Storage::disk('local')->assertExists($media->path);
        $this->assertNull($media->checksum_sha256);
        Queue::assertPushedOn('media', ProcessMedia::class, fn ($job) => $job->media->is($media));
    }

    public function test_file_type_must_match_declared_kind(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/media', ['kind' => 'video', 'file' => UploadedFile::fake()->image('not-video.jpg')])->assertUnprocessable()->assertJsonValidationErrors('file');
        Queue::assertNothingPushed();
    }

    public function test_php_rejected_upload_returns_validation_error_instead_of_crashing(): void
    {
        $user = User::factory()->create();
        $file = new UploadedFile('', 'too-large.mp4', 'video/mp4', UPLOAD_ERR_INI_SIZE, true);

        $this->actingAs($user, 'sanctum')->post('/api/v1/media', [
            'kind' => 'video',
            'file' => $file,
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        Queue::assertNothingPushed();
    }

    public function test_video_trim_selection_is_queued_with_the_upload(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('match.mp4', 1024, 'video/mp4');

        $this->actingAs($user, 'sanctum')->post('/api/v1/media', [
            'kind' => 'video',
            'collection' => 'highlights',
            'trim_start_ms' => 90000,
            'trim_end_ms' => 150000,
            'file' => $file,
        ], ['Accept' => 'application/json'])->assertAccepted();

        $this->assertSame(['trim_start_ms' => 90000, 'trim_end_ms' => 150000], Media::firstOrFail()->metadata);
        Queue::assertPushedOn('media', ProcessMedia::class);
    }

    public function test_video_trim_cannot_exceed_sixty_seconds(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->post('/api/v1/media', [
            'kind' => 'video',
            'trim_start_ms' => 0,
            'trim_end_ms' => 61000,
            'file' => UploadedFile::fake()->create('match.mp4', 1024, 'video/mp4'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('trim_end_ms');
    }

    public function test_user_can_list_and_delete_own_media(): void
    {
        $user = User::factory()->create();
        $media = Media::factory()->for($user)->create(['disk' => 'local', 'path' => 'users/'.$user->id.'/image/test.jpg']);
        Storage::disk('local')->put($media->path, 'image');
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/media')->assertOk()->assertJsonPath('data.0.id', $media->public_id);
        $this->deleteJson('/api/v1/media/'.$media->public_id)->assertOk();
        Storage::disk('local')->assertMissing($media->path);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    public function test_pending_media_is_private_to_owner(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $media = Media::factory()->for($owner)->create(['moderation_status' => 'pending']);
        $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/media/'.$media->public_id)->assertForbidden();
    }

    public function test_owner_can_classify_and_describe_library_file(): void
    {
        $user = User::factory()->create();
        $media = Media::factory()->for($user)->create(['kind' => 'document', 'collection' => 'uploads']);

        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/media/'.$media->public_id, [
            'title' => '2026 Football CV',
            'description' => 'Current playing résumé.',
            'collection' => 'resumes',
        ])->assertOk()->assertJsonPath('data.title', '2026 Football CV')->assertJsonPath('data.collection', 'resumes');
    }

    public function test_media_attached_to_a_post_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $media = Media::factory()->for($user)->create();
        Video::factory()->for($user)->create(['media_id' => $media->id]);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/media/'.$media->public_id)
            ->assertConflict()->assertJsonPath('message', 'This file is currently attached to a post, message, or application and cannot be deleted.');
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }
}
