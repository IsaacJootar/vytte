<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_web_responses_include_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Strict-Transport-Security', 'max-age=15552000');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_guest_logo_returns_to_the_vytte_login_route(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('href="'.route('login').'"', false);
    }

    public function test_health_response_also_includes_security_headers(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertHeader('Content-Security-Policy')
            ->assertHeader('Strict-Transport-Security', 'max-age=15552000')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
