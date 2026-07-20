<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_web_responses_include_security_headers_and_skip_link(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-site')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none')
            ->assertSee('Skip to main content');

        $response->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_api_errors_also_include_security_headers(): void
    {
        $this->getJson('/api/v1/profile')
            ->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_hsts_is_only_added_for_secure_production_requests(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
        $this->app['env'] = 'production';
        $response = $this->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        $this->assertStringContainsString("default-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'self'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('form-action \'self\' https://sandbox.payfast.co.za https://www.payfast.co.za', (string) $response->headers->get('Content-Security-Policy'));
    }
}
