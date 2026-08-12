<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_added_to_application_responses(): void
    {
        config([
            'security.headers_enabled' => true,
            'security.csp_mode' => 'report-only',
            'security.hsts_max_age' => 300,
        ]);

        $response = $this->get('https://localhost/up');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Strict-Transport-Security', 'max-age=300');
        $response->assertHeader('Content-Security-Policy-Report-Only');
        $response->assertHeader('Permissions-Policy');
        $this->assertFalse($response->headers->has('X-Powered-By'));
    }

    public function test_hsts_is_not_sent_over_plain_http(): void
    {
        config([
            'security.headers_enabled' => true,
            'security.hsts_max_age' => 300,
        ]);

        $response = $this->get('/up');

        $response->assertOk();
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    public function test_headers_can_be_disabled_for_emergency_rollback(): void
    {
        config(['security.headers_enabled' => false]);

        $response = $this->get('/up');

        $response->assertOk();
        $this->assertFalse($response->headers->has('Content-Security-Policy-Report-Only'));
        $this->assertFalse($response->headers->has('X-Frame-Options'));
    }
}
