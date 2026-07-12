<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Media\Jobs\ProcessMedia;
use App\Domain\Media\Models\Media;
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
        Queue::assertPushed(ProcessMedia::class, fn ($job) => $job->media->is($media));
    }

    public function test_file_type_must_match_declared_kind(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/media', ['kind' => 'video', 'file' => UploadedFile::fake()->image('not-video.jpg')])->assertUnprocessable()->assertJsonValidationErrors('file');
        Queue::assertNothingPushed();
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
}
