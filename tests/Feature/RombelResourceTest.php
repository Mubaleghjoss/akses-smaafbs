<?php

namespace Tests\Feature;

use App\Models\DataSiswa;
use App\Models\Rombel;
use App\Models\User;
use App\Support\DataSiswa\DataSiswaSupport;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\BootstrapsAdminFeatureTables;
use Tests\TestCase;

class RombelResourceTest extends TestCase
{
    use BootstrapsAdminFeatureTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapAdminFeatureTables();
    }

    public function test_data_siswa_creates_and_prunes_empty_rombel_master(): void
    {
        $rombelName = 'TEST ROMBEL '.Str::upper(Str::random(8));

        Rombel::query()->where('nama', $rombelName)->delete();

        $student = DataSiswa::query()->create([
            'nama' => 'Siswa Rombel Prune',
            'rombel_saat_ini' => $rombelName,
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $this->assertDatabaseHas('rombels', [
            'nama' => $rombelName,
        ]);

        $student->delete();

        $this->assertDatabaseMissing('rombels', [
            'nama' => $rombelName,
        ]);
    }

    public function test_renaming_rombel_updates_students_using_old_name(): void
    {
        $oldName = 'TEST OLD '.Str::upper(Str::random(8));
        $newName = 'TEST NEW '.Str::upper(Str::random(8));

        Rombel::query()->whereIn('nama', [$oldName, $newName])->delete();

        $rombel = Rombel::query()->create([
            'nama' => $oldName,
            'is_active' => true,
        ]);

        $student = DataSiswa::query()->create([
            'nama' => 'Siswa Rename Rombel',
            'rombel_saat_ini' => $oldName,
            'jk' => 'P',
            'status' => 'aktif',
        ]);

        $rombel->update([
            'nama' => $newName,
        ]);

        $this->assertSame($newName, $student->fresh()->rombel_saat_ini);
        $this->assertDatabaseHas('rombels', [
            'nama' => $newName,
        ]);
    }

    public function test_empty_master_rombel_is_available_in_options_for_admin(): void
    {
        $rombelName = 'TEST EMPTY '.Str::upper(Str::random(8));

        Rombel::query()->where('nama', $rombelName)->delete();
        Rombel::query()->create([
            'nama' => $rombelName,
            'is_active' => true,
        ]);

        $admin = User::query()->create([
            'name' => 'Admin Rombel Options',
            'username' => 'admin-rombel-options-'.Str::lower(Str::random(6)),
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $this->assertArrayHasKey($rombelName, DataSiswaSupport::rombelOptions($admin));
    }
}
