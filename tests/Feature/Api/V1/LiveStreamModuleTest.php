<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_host_heartbeat_keeps_stream_visible_and_stale_streams_are_ended(): void
    {
        $host = User::factory()->create();
        $stream = $this->actingAs($host, 'sanctum')->postJson('/api/v1/live', ['title' => 'Evening training'])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/live/$stream/heartbeat")->assertOk();
        $this->assertDatabaseHas('live_streams', ['public_id' => $stream, 'status' => 'live']);

        DB::table('live_streams')->where('public_id', $stream)->update(['updated_at' => now()->subMinute()]);
        $this->getJson('/api/v1/live')->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('live_streams', ['public_id' => $stream, 'status' => 'ended']);
    }

    public function test_only_host_can_end_stream_and_repeated_end_is_safe(): void
    {
        $host = User::factory()->create();
        $viewer = User::factory()->create();
        $stream = $this->actingAs($host, 'sanctum')->postJson('/api/v1/live', ['title' => 'Match day'])->assertCreated()->json('data.id');

        $this->actingAs($viewer, 'sanctum')->postJson("/api/v1/live/$stream/end")->assertForbidden();
        $this->actingAs($host, 'sanctum')->postJson("/api/v1/live/$stream/end")->assertOk();
        $this->postJson("/api/v1/live/$stream/end")->assertOk();
    }
}
