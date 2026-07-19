<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_missing_web_pages_use_the_branded_404_experience(): void
    {
        config(['app.debug' => false]);

        $this->get('/a-page-that-does-not-exist')
            ->assertNotFound()
            ->assertSee('This page is out of play.')
            ->assertSee('SportsUniverse');
    }

    public function test_forbidden_and_server_errors_use_branded_pages(): void
    {
        config(['app.debug' => false]);
        Route::get('/test-errors/forbidden', fn () => abort(403));
        Route::get('/test-errors/server', fn () => throw new \RuntimeException('Test exception'));

        $this->get('/test-errors/forbidden')->assertForbidden()->assertSee('You don’t have access to this.');
        $this->get('/test-errors/server')->assertStatus(500)->assertSee('We dropped the ball.');
    }

    public function test_expired_sessions_get_a_dedicated_recovery_page(): void
    {
        Route::get('/test-errors/expired', fn () => abort(419));

        $this->get('/test-errors/expired')->assertRedirect('/session-expired');
        $this->get('/session-expired')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Errors/Show')
            ->where('status', 419));
    }

    public function test_api_errors_remain_json(): void
    {
        $this->getJson('/api/v1/a-page-that-does-not-exist')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json');
    }
}
