<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatefulApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_application_host_is_always_a_stateful_sanctum_domain(): void
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST);
        $port = parse_url(config('app.url'), PHP_URL_PORT);
        $domain = $host.($port ? ':'.$port : '');

        $this->assertContains($domain, config('sanctum.stateful'));
    }

    public function test_browser_session_can_authenticate_a_same_origin_api_request(): void
    {
        $user = User::factory()->create();
        $origin = rtrim(config('app.url'), '/');

        $this->actingAs($user)
            ->withHeaders(['Origin' => $origin, 'Referer' => $origin.'/admin'])
            ->getJson('/api/v1/opportunities?per_page=50')
            ->assertOk();
    }

    public function test_page_performance_telemetry_does_not_require_authentication(): void
    {
        $this->postJson('/api/v1/page-performance', [
            'kind' => 'navigation',
            'path' => '/',
            'duration_ms' => 250,
        ])->assertAccepted();
    }
}
