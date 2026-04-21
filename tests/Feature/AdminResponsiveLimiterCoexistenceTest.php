<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\BootstrapsAdminFeatureTables;
use Tests\TestCase;

class AdminResponsiveLimiterCoexistenceTest extends TestCase
{
    use BootstrapsAdminFeatureTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapAdminFeatureTables();
    }

    public function test_endpoint_protection_matrix_explicitly_declares_admin_async_exemption(): void
    {
        $category = config('endpoint_protection.endpoint_categories.admin_panel_async');

        $this->assertIsArray($category);
        $this->assertSame(null, $category['named_limiter']);
        $this->assertStringContainsString('without route-level throttle', (string) ($category['notes'] ?? ''));
        $this->assertContains('/livewire-{asset_hash}/update', $category['routes'] ?? []);
    }

    public function test_livewire_update_route_has_no_route_throttle_middleware(): void
    {
        $route = Route::getRoutes()->getByName('default-livewire.update');

        $this->assertNotNull($route, 'Expected Livewire update route to be registered.');

        $middleware = $route->gatherMiddleware();

        $this->assertFalse(
            collect($middleware)->contains(fn (string $item): bool => str_starts_with($item, 'throttle')),
            'Livewire update route should stay free from route-level throttle middleware to preserve normal Filament async behavior.'
        );
    }

    public function test_admin_login_route_has_no_route_throttle_middleware_because_throttle_is_livewire_based(): void
    {
        $route = Route::getRoutes()->getByName('filament.admin.auth.login');

        $this->assertNotNull($route, 'Expected admin login route to be registered.');

        $middleware = $route->gatherMiddleware();

        $this->assertFalse(
            collect($middleware)->contains(fn (string $item): bool => str_starts_with($item, 'throttle')),
            'Admin login route should not stack route-level throttle on top of Filament Livewire login throttling.'
        );
    }

    public function test_authenticated_admin_users_pages_render_under_degraded_mode(): void
    {
        config()->set('endpoint_protection.graceful_degradation.enabled', true);
        config()->set('endpoint_protection.graceful_degradation.profiles.admin_heavy_widgets.menu.skip_expensive_dynamic_sections', true);
        config()->set('endpoint_protection.graceful_degradation.profiles.admin_heavy_widgets.dashboard.skip_expensive_widgets', true);

        $admin = $this->createAdminUser();

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Akun Admin');

        $this->actingAs($admin)
            ->get('/admin/users/create')
            ->assertOk()
            ->assertSee('Akun Pengguna Admin');
    }

    protected function createAdminUser(): User
    {
        $admin = User::query()->create([
            'name' => 'Integration Admin',
            'username' => 'integration.admin',
            'email' => 'integration-admin@example.test',
            'password' => Hash::make('secret123'),
        ]);

        $adminRole = Role::findOrCreate('admin', 'web');
        $admin->assignRole($adminRole);

        return $admin;
    }
}
