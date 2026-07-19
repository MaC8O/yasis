<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_attached_to_web_responses(): void
    {
        // A failed login returns a plain redirect (no @vite view render), which still
        // passes through the web middleware group where SecurityHeaders lives.
        $response = $this->from('/login')->post('/login', [
            'email' => 'nobody@test.local',
            'password' => 'wrong-password',
        ]);

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp, 'CSP header should be present when the Vite dev server is not hot.');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_hsts_is_not_sent_over_plain_http(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'nobody@test.local',
            'password' => 'wrong-password',
        ]);

        $response->assertHeaderMissing('Strict-Transport-Security');
    }
}
