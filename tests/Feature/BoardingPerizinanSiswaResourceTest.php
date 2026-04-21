<?php

namespace Tests\Feature;

use App\Filament\Resources\BoardingPerizinanSiswaResource;
use App\Filament\Resources\BoardingPerizinanSiswaResource\Pages\ListBoardingPerizinanSiswas;
use App\Models\BoardingPerizinanSiswa;
use App\Models\DataSiswa;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class BoardingPerizinanSiswaResourceTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        $this->createDataSiswaTable();
        $this->runPerizinanMigrations();
        BoardingPerizinanSiswa::flushRuntimeSchemaCache();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_pamong_can_create_perizinan_from_student_table_and_open_history_page(): void
    {
        $pamong = User::query()->create([
            'name' => 'Pamong Perizinan',
            'username' => 'pamong-perizinan',
            'password' => bcrypt('password'),
            'boarding_rombel_scope' => ['XI.I / 2025-2026'],
        ]);
        $pamong->assignRole('pamong_putra');

        $student = DataSiswa::query()->create([
            'nama' => 'Santri Perizinan',
            'rombel_saat_ini' => 'XI.I / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $listPage = Livewire::actingAs($pamong)
            ->test(ListBoardingPerizinanSiswas::class)
            ->callTableAction('isiPerizinan', $student, [
                'judul_izin' => 'Jenguk Orang Tua',
                'tanggal_izin' => '2026-04-05',
                'waktu_izin' => '09:15',
                'detail_izin' => 'Dijemput wali dari gerbang utama.',
                'approval_mode' => 'akun',
                'diizinkan_oleh_user_id' => $pamong->id,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('boarding_perizinan_siswas', [
            'siswa_id' => $student->id,
            'judul_izin' => 'Jenguk Orang Tua',
            'dibuat_oleh' => $pamong->id,
        ]);

        $recordUrl = $listPage->instance()->getTable()->getRecordUrl($student);

        $this->assertSame(BoardingPerizinanSiswaResource::getUrl('view', ['record' => $student]), $recordUrl);

        $this->actingAs($pamong)
            ->get($recordUrl)
            ->assertOk()
            ->assertSee('Ringkasan Murid')
            ->assertSee('Riwayat Perizinan Boarding')
            ->assertSee('Jenguk Orang Tua')
            ->assertSee('Dijemput wali dari gerbang utama.');
    }

    protected function createDataSiswaTable(): void
    {
        if (Schema::hasTable('data_siswa')) {
            return;
        }

        Schema::create('data_siswa', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->string('rombel_saat_ini')->nullable();
            $table->string('jk', 2)->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    protected function runPerizinanMigrations(): void
    {
        if (! Schema::hasTable('boarding_perizinan_siswas')) {
            $migration = require database_path('migrations/2026_03_30_090000_create_boarding_perizinan_siswas_table.php');
            $migration->up();
        }

        $syncMigration = require database_path('migrations/2026_03_31_000100_sync_boarding_perizinan_runtime_columns.php');
        $syncMigration->up();

        $approvalMigration = require database_path('migrations/2026_04_03_194000_add_approval_fields_to_boarding_perizinan_siswas_table.php');
        $approvalMigration->up();
    }
}
