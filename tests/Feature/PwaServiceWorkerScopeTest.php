<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaServiceWorkerScopeTest extends TestCase
{
    public function test_service_worker_route_is_exposed_with_javascript_content_type(): void
    {
        $response = $this->get('/service-worker.js');

        $response->assertOk();
        $this->assertStringContainsString('application/javascript', (string) $response->headers->get('content-type'));
        $this->assertSame('/', $response->headers->get('Service-Worker-Allowed'));
    }

    public function test_service_worker_explicitly_bypasses_admin_auth_livewire_and_upload_paths(): void
    {
        $this->get('/service-worker.js')
            ->assertOk()
            ->assertSee("request.method !== 'GET'", false)
            ->assertSee("'/admin'", false)
            ->assertSee("'/livewire'", false)
            ->assertSee("'/storage'", false)
            ->assertSee("'/login'", false)
            ->assertSee("'/logout'", false)
            ->assertSee('shouldBypassCache', false)
            ->assertSee('event.respondWith(fetch(request));', false);
    }
}
