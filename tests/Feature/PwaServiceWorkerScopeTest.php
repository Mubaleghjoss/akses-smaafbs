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
        $this->assertFalse($response->headers->has('Set-Cookie'));
    }

    public function test_service_worker_explicitly_bypasses_admin_auth_livewire_and_upload_paths(): void
    {
        $this->get('/service-worker.js')
            ->assertOk()
            ->assertSee("const CACHE_NAME = 'akses-public-shell-v7'", false)
            ->assertSee("request.method !== 'GET'", false)
            ->assertSee("'/admin'", false)
            ->assertSee("'/livewire'", false)
            ->assertSee("'/storage'", false)
            ->assertSee("'/build'", false)
            ->assertSee("'/login'", false)
            ->assertSee("'/logout'", false)
            ->assertSee('shouldPassThrough', false)
            ->assertSee('if (shouldPassThrough(request, url))', false)
            ->assertSee('return;', false)
            ->assertSee('offlineResponse', false)
            ->assertSee('status: 503', false)
            ->assertSee("request.mode === 'navigate'", false)
            ->assertSee('[503, 504].includes(response.status)', false)
            ->assertSee("offlineResponse('network_error', 0)", false)
            ->assertSee('Periksa Koneksi Server', false)
            ->assertSee("fetch('/up?connectivity_probe='", false)
            ->assertDontSee("cache.addAll(['/'])", false)
            ->assertDontSee('cache.put(request', false);
    }

    public function test_shared_pwa_registrar_reuses_existing_registration_and_limits_updates(): void
    {
        $script = file_get_contents(public_path('js/pwa-registration.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('getRegistration(\'/\')', $script);
        $this->assertStringContainsString('6 * 60 * 60 * 1000', $script);
        $this->assertStringContainsString('if (!existing)', $script);
        $this->assertStringContainsString('registration.update()', $script);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('js/pwa-registration.js', false)
            ->assertDontSee('.then((registration) => registration.update())', false);
    }

    public function test_manifest_can_be_cached_for_one_day(): void
    {
        $response = $this->get('/manifest.webmanifest');

        $response->assertOk();
        $this->assertStringContainsString(
            'max-age=86400',
            (string) $response->headers->get('Cache-Control'),
        );
    }

    public function test_admin_responses_disable_browser_cache(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk()
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0');

        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
    }
}
