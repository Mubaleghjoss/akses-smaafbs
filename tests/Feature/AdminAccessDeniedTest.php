<?php

namespace Tests\Feature;

use App\Models\GuruTendik;
use App\Models\User;
use App\Support\Admin\AdminAccessDenied;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as HttpRoute;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\Feature\Concerns\BootstrapsAdminFeatureTables;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
class AdminAccessDeniedTest extends TestCase
{
    use BootstrapsAdminFeatureTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapAdminFeatureTables();
    }

    public function test_limited_guru_is_redirected_with_access_popup_state_instead_of_raw_forbidden(): void
    {
        $guru = GuruTendik::query()->create([
            'nama' => 'Ustadz Akses Terbatas',
            'nip' => '1987007',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
        ]);

        $guruUser = User::query()->create([
            'name' => 'Akses Terbatas',
            'username' => 'akses-terbatas',
            'password' => 'secret123',
            'guru_tendik_id' => $guru->id,
        ]);
        $guruUser->assignRole('guru');
        $guruUser->syncPermissions(['guru_tendik.view']);

        $response = $this->actingAs($guruUser)
            ->from('/admin/guru-tendiks')
            ->get('/admin/data-siswas?chart_status=aktif&chart_rombel=X.I%20%2F%202025-2026&chart_jk=L');

        $response->assertRedirect('/admin/guru-tendiks');
        $response->assertSessionHas(AdminAccessDenied::FLASH_KEY, fn (array $state): bool => str_contains((string) ($state['message'] ?? ''), 'belum diberi akses'));

        $this->actingAs($guruUser)
            ->followingRedirects()
            ->from('/admin/guru-tendiks')
            ->get('/admin/data-siswas?chart_status=aktif&chart_rombel=X.I%20%2F%202025-2026&chart_jk=L')
            ->assertSee('Pemberitahuan Akses')
            ->assertSee('Akses dibatasi');
    }

    public function test_forbidden_admin_export_redirects_with_access_popup_state(): void
    {
        $guru = GuruTendik::query()->create([
            'nama' => 'Ustadz Export Terbatas',
            'nip' => '1987008',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
        ]);

        $guruUser = User::query()->create([
            'name' => 'Export Terbatas',
            'username' => 'export-terbatas',
            'password' => 'secret123',
            'guru_tendik_id' => $guru->id,
        ]);
        $guruUser->assignRole('guru');
        $guruUser->syncPermissions(['guru_tendik.view']);

        $response = $this->actingAs($guruUser)->get(route('admin.data-siswa.export'));

        $response->assertRedirect('/admin');
        $response->assertSessionHas(AdminAccessDenied::FLASH_KEY, fn (array $state): bool => ($state['title'] ?? null) === 'Akses dibatasi');
    }

    public function test_admin_access_denied_helper_skips_livewire_requests(): void
    {
        $request = Request::create('/admin/data-siswas', 'GET');
        $request->headers->set('X-Livewire', 'true');
        $request->setUserResolver(function (): User {
            return User::query()->create([
                'name' => 'Pamong Livewire',
                'username' => 'pamong-livewire',
                'password' => 'secret123',
            ]);
        });

        $this->assertFalse(AdminAccessDenied::shouldHandle($request));
    }

    public function test_admin_access_denied_helper_skips_livewire_update_routes(): void
    {
        $request = Request::create('/admin/livewire/update', 'GET');
        $request->setUserResolver(function (): User {
            return User::query()->create([
                'name' => 'Pamong Livewire Route',
                'username' => 'pamong-livewire-route',
                'password' => 'secret123',
            ]);
        });
        $request->setRouteResolver(function (): HttpRoute {
            return (new HttpRoute(['GET'], '/admin/livewire/update', fn () => null))
                ->name('testing.livewire.update');
        });

        $this->assertFalse(AdminAccessDenied::shouldHandle($request));
    }

    public function test_admin_access_denied_helper_rejects_external_admin_referer_redirects(): void
    {
        $guru = GuruTendik::query()->create([
            'nama' => 'Ustadz Referer Terbatas',
            'nip' => '1987009',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
        ]);

        $guruUser = User::query()->create([
            'name' => 'Referer Terbatas',
            'username' => 'referer-terbatas',
            'password' => 'secret123',
            'guru_tendik_id' => $guru->id,
        ]);
        $guruUser->assignRole('guru');
        $guruUser->syncPermissions(['guru_tendik.view']);

        $response = $this->actingAs($guruUser)
            ->withHeader('referer', 'https://evil.example.com/admin/guru-tendiks?x=1')
            ->get('/admin/data-siswas?chart_status=aktif');

        $response->assertRedirect('/admin');
    }

    public function test_admin_access_denied_helper_falls_back_when_referer_matches_current_path(): void
    {
        $guru = GuruTendik::query()->create([
            'nama' => 'Ustadz Same Path',
            'nip' => '1987010',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
        ]);

        $guruUser = User::query()->create([
            'name' => 'Same Path',
            'username' => 'same-path-terbatas',
            'password' => 'secret123',
            'guru_tendik_id' => $guru->id,
        ]);
        $guruUser->assignRole('guru');
        $guruUser->syncPermissions(['guru_tendik.view']);

        $response = $this->actingAs($guruUser)
            ->withHeader('referer', 'http://localhost/admin/data-siswas?chart_status=aktif')
            ->get('/admin/data-siswas?chart_status=aktif');

        $response->assertRedirect('/admin');
    }
}
