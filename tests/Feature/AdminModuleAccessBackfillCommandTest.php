<?php

namespace Tests\Feature;

use App\Models\GuruTendik;
use App\Models\User;
use App\Support\Admin\AdminModuleAccess;
use Tests\Feature\Concerns\BootstrapsAdminFeatureTables;
use Tests\TestCase;

class AdminModuleAccessBackfillCommandTest extends TestCase
{
    use BootstrapsAdminFeatureTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapAdminFeatureTables();
    }

    public function test_backfill_writes_effective_levels_for_unmigrated_panel_user(): void
    {
        $guru = GuruTendik::query()->create([
            'nama' => 'Ustadz Backfill',
            'nip' => '1987011',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
        ]);

        $user = User::query()->create([
            'name' => 'Backfill Guru',
            'username' => 'backfill-guru',
            'password' => 'secret123',
            'guru_tendik_id' => $guru->id,
        ]);
        $user->assignRole('guru');

        $this->assertNull($user->getRawOriginal('module_access_levels'));

        $this->artisan('app:backfill-module-access-levels')
            ->assertExitCode(0);

        $user->refresh();

        $this->assertSame(AdminModuleAccess::MANAGE, $user->moduleAccessLevel('guru_tendik'));
        $this->assertSame(AdminModuleAccess::MANAGE, $user->moduleAccessLevel('berkas_guru'));
        $this->assertSame(AdminModuleAccess::NONE, $user->moduleAccessLevel('data_siswa'));
        $this->assertNotNull($user->getRawOriginal('module_access_levels'));
    }

    public function test_backfill_skips_users_with_explicit_levels_by_default(): void
    {
        $guru = GuruTendik::query()->create([
            'nama' => 'Ustadz Explicit',
            'nip' => '1987012',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
        ]);

        $user = User::query()->create([
            'name' => 'Explicit Guru',
            'username' => 'explicit-guru',
            'password' => 'secret123',
            'guru_tendik_id' => $guru->id,
            'module_access_levels' => [
                'guru_tendik' => AdminModuleAccess::VIEW,
                'berkas_guru' => AdminModuleAccess::VIEW,
            ],
        ]);
        $user->assignRole('guru');

        $this->artisan('app:backfill-module-access-levels')
            ->expectsOutputToContain('skip_explicit')
            ->assertExitCode(0);

        $user->refresh();

        $this->assertSame(AdminModuleAccess::VIEW, $user->moduleAccessLevel('guru_tendik'));
    }

    public function test_backfill_skips_admin_users(): void
    {
        $admin = User::query()->create([
            'name' => 'Another Admin',
            'username' => 'another-admin',
            'password' => 'secret123',
        ]);
        $admin->assignRole('admin');

        $this->artisan('app:backfill-module-access-levels')
            ->assertExitCode(0);

        $admin->refresh();

        $this->assertNull($admin->getRawOriginal('module_access_levels'));
    }

    public function test_backfill_is_idempotent_on_second_run(): void
    {
        $guru = GuruTendik::query()->create([
            'nama' => 'Ustadz Idempotent',
            'nip' => '1987013',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
        ]);

        $user = User::query()->create([
            'name' => 'Idempotent Guru',
            'username' => 'idempotent-guru',
            'password' => 'secret123',
            'guru_tendik_id' => $guru->id,
        ]);
        $user->assignRole('guru');

        $this->artisan('app:backfill-module-access-levels')
            ->assertExitCode(0);

        $firstState = $user->fresh()->module_access_levels;

        $this->artisan('app:backfill-module-access-levels')
            ->assertExitCode(0);

        $this->assertSame($firstState, $user->fresh()->module_access_levels);
    }
}
