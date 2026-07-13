<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveStreamModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_start_chat_in_and_end_a_live_session(): void
    {
        $host = User::factory()->create(['name' => 'Live Host']);
        $viewer = User::factory()->create(['name' => 'Live Viewer']);

        $stream = $this->actingAs($host, 'sanctum')->postJson('/api/v1/live', ['title' => 'Training session', 'description' => 'Live drills'])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/live')->assertOk()->assertJsonPath('data.0.title', 'Training session');
        $this->actingAs($viewer, 'sanctum')->postJson("/api/v1/live/$stream/join")->assertOk()->assertJsonPath('data.viewer_count', 1);
        $this->postJson("/api/v1/live/$stream/messages", ['body' => 'Great session!'])->assertCreated()->assertJsonPath('data.name', 'Live Viewer');
        $this->actingAs($host, 'sanctum')->postJson("/api/v1/live/$stream/end")->assertOk();
        $this->assertDatabaseHas('live_streams', ['public_id' => $stream, 'status' => 'ended']);
    }
}
