<?php

namespace Tests\Feature;

use App\Filament\Resources\BoardingKonselingMtResource;
use App\Filament\Resources\BoardingKonselingMtResource\Pages\ListBoardingKonselingMts;
use App\Models\DataSiswa;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class BoardingKonselingMtResourceTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        $this->createDataSiswaTable();
        $this->runBoardingMigrations();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_pamong_can_create_boarding_note_from_student_table_and_open_history_page(): void
    {
        $pamong = User::query()->create([
            'name' => 'Pamong Resource',
            'username' => 'pamong-resource',
            'password' => bcrypt('password'),
            'boarding_rombel_scope' => ['XI.I / 2025-2026'],
        ]);
        $pamong->assignRole('pamong_putra');

        $student = DataSiswa::query()->create([
            'nama' => 'Santri Boarding',
            'rombel_saat_ini' => 'XI.I / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        DataSiswa::query()->create([
            'nama' => 'Santri Tidak Terlihat',
            'rombel_saat_ini' => 'XI.I / 2025-2026',
            'jk' => 'P',
            'status' => 'aktif',
        ]);

        $listPage = Livewire::actingAs($pamong)
            ->test(ListBoardingKonselingMts::class)
            ->callTableAction('isiKonseling', $student, [
                'tanggal_konseling' => '2026-04-05',
                'ringkasan_masalah' => 'Disiplin asrama',
                'tindak_lanjut' => 'Santri menyepakati jadwal piket malam dan evaluasi pekanan.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('boarding_konseling_mts', [
            'siswa_id' => $student->id,
            'pamong_user_id' => $pamong->id,
            'ringkasan_masalah' => 'Disiplin asrama',
            'tindak_lanjut' => 'Santri menyepakati jadwal piket malam dan evaluasi pekanan.',
        ]);

        $recordUrl = $listPage->instance()->getTable()->getRecordUrl($student);

        $this->assertSame(BoardingKonselingMtResource::getUrl('view', ['record' => $student]), $recordUrl);

        $this->actingAs($pamong)
            ->get($recordUrl)
            ->assertOk()
            ->assertSee('Ringkasan Murid')
            ->assertSee('Riwayat Konseling Boarding')
            ->assertSee('Disiplin asrama')
            ->assertSee('Santri menyepakati jadwal piket malam dan evaluasi pekanan.');
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

    protected function runBoardingMigrations(): void
    {
        if (! Schema::hasTable('boarding_konseling_mts')) {
            $migration = require database_path('migrations/2026_03_25_203000_create_boarding_management_tables.php');
            $migration->up();
        }

        $ownerMigration = require database_path('migrations/2026_03_26_090000_add_boarding_detail_and_pamong_owner_fields.php');
        $ownerMigration->up();

        $materiRapotScopeMigration = require database_path('migrations/2026_05_31_080000_add_materi_rapot_scope_to_boarding_pencapaians.php');
        $materiRapotScopeMigration->up();
    }
}
