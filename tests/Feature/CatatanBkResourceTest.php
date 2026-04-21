<?php

namespace Tests\Feature;

use App\Filament\Resources\CatatanBkResource;
use App\Filament\Resources\CatatanBkResource\Pages\ListCatatanBks;
use App\Models\DataSiswa;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class CatatanBkResourceTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        $this->createDataSiswaTable();
        $this->runCatatanBkMigration();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_can_create_bk_note_from_student_table_and_open_history_page(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin BK',
            'username' => 'admin-bk',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $student = DataSiswa::query()->create([
            'nama' => 'Siswa BK',
            'nipd' => '2026001',
            'nisn' => '1234567890',
            'rombel_saat_ini' => 'XI IPA 1',
            'status' => 'aktif',
        ]);

        $listPage = Livewire::actingAs($admin)
            ->test(ListCatatanBks::class)
            ->callTableAction('isiKonseling', $student, [
                'tanggal_konseling' => '2026-04-05',
                'topik_pembahasan' => 'Disiplin belajar',
                'hasil_konseling' => 'Siswa menyepakati jadwal belajar sore dan evaluasi mingguan.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('catatan_bks', [
            'siswa_id' => $student->id,
            'topik_pembahasan' => 'Disiplin belajar',
        ]);

        $recordUrl = $listPage->instance()->getTable()->getRecordUrl($student);

        $this->assertSame(CatatanBkResource::getUrl('view', ['record' => $student]), $recordUrl);

        $this->actingAs($admin)
            ->get($recordUrl)
            ->assertOk()
            ->assertSee('Ringkasan Murid')
            ->assertSee('Riwayat Konseling BK')
            ->assertSee('Disiplin belajar')
            ->assertSee('Siswa menyepakati jadwal belajar sore dan evaluasi mingguan.');
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
            $table->string('billing_code')->nullable();
            $table->string('wa_ortu')->nullable();
            $table->string('nipd')->nullable();
            $table->string('jk', 2)->nullable();
            $table->string('nisn')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    protected function runCatatanBkMigration(): void
    {
        if (Schema::hasTable('catatan_bks')) {
            return;
        }

        $migration = require database_path('migrations/2026_04_05_100000_create_catatan_bks_table.php');
        $migration->up();
    }
}

