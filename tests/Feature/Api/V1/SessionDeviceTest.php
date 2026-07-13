<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionDeviceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_and_revoke_only_their_other_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $other = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'users-other-device', 'user_id' => $user->id, 'ip_address' => '10.0.0.2', 'user_agent' => 'Mozilla/5.0 (iPhone) AppleWebKit Safari/605.1', 'payload' => '', 'last_activity' => now()->timestamp],
            ['id' => 'somebody-elses-device', 'user_id' => $other->id, 'ip_address' => '10.0.0.3', 'user_agent' => 'Chrome/120 Windows', 'payload' => '', 'last_activity' => now()->timestamp],
        ]);

        $this->actingAs($user)->getJson('/api/v1/auth/sessions')->assertOk()->assertJsonFragment(['id' => 'users-other-device', 'platform' => 'iOS']);
        $this->deleteJson('/api/v1/auth/sessions/users-other-device')->assertOk();
        $this->assertDatabaseMissing('sessions', ['id' => 'users-other-device']);
        $this->deleteJson('/api/v1/auth/sessions/somebody-elses-device')->assertOk();
        $this->assertDatabaseHas('sessions', ['id' => 'somebody-elses-device']);
    }
}
