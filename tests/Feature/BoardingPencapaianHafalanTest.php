<?php

namespace Tests\Feature;

use App\Filament\Resources\BoardingHafalanPointResource;
use App\Filament\Resources\BoardingPencapaianResource;
use App\Filament\Resources\BoardingPencapaianResource\Pages\ManageBoardingPencapaians;
use App\Filament\Resources\BoardingPencapaianResource\Pages\ManageHafalan;
use App\Models\BoardingBacaanAssessment;
use App\Models\BoardingHafalanAssessment;
use App\Models\BoardingHafalanPoint;
use App\Models\BoardingMaknaProgress;
use App\Models\BoardingPencapaian;
use App\Models\DataSiswa;
use App\Models\User;
use App\Support\Admin\AdminAccessDenied;
use App\Support\Admin\AdminModuleAccess;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class BoardingPencapaianHafalanTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        $this->createDataSiswaTable();
        $this->runBoardingMigrations();
        $this->runBoardingHafalanMigration();
        $this->runBoardingMaknaAndBacaanMigration();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
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
        if (! Schema::hasTable('boarding_rapots')) {
            $migration = require database_path('migrations/2026_03_25_203000_create_boarding_management_tables.php');
            $migration->up();
        }

        $expandMigration = require database_path('migrations/2026_03_25_231000_expand_boarding_progress_and_rapot_tables.php');
        $expandMigration->up();

        $detailMigration = require database_path('migrations/2026_03_26_090000_add_boarding_detail_and_pamong_owner_fields.php');
        $detailMigration->up();
    }

    protected function runBoardingHafalanMigration(): void
    {
        if (Schema::hasTable('boarding_hafalan_assessments')) {
            return;
        }

        $migration = require database_path('migrations/2026_04_02_120000_create_boarding_hafalan_tables.php');
        $migration->up();
    }

    protected function runBoardingMaknaAndBacaanMigration(): void
    {
        if (Schema::hasTable('boarding_bacaan_assessments')) {
            return;
        }

        $migration = require database_path('migrations/2026_04_03_220000_create_boarding_makna_and_bacaan_tables.php');
        $migration->up();
    }

    protected function makePencapaianRecord(): BoardingPencapaian
    {
        $siswa = DataSiswa::query()->create([
            'nama' => 'Santri Hafalan',
            'rombel_saat_ini' => 'XI IPA 1',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        return BoardingPencapaian::query()->create([
            'siswa_id' => $siswa->id,
            'status_pencapaian' => 'proses',
        ]);
    }


    public function test_index_page_shows_material_breakdown_and_total_percentage_summary(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Boarding Summary',
            'username' => 'admin-boarding-summary',
            'password' => 'secret123',
        ]);
        $admin->assignRole('admin');

        $record = $this->makePencapaianRecord();

        $pegonPoint = BoardingHafalanPoint::query()
            ->where('materi_key', 'pegon_bacaan')
            ->where('is_active', true)
            ->orderBy('urutan')
            ->firstOrFail();

        $cepatanPoint = BoardingHafalanPoint::query()
            ->where('materi_key', 'cepatan')
            ->where('is_active', true)
            ->orderBy('urutan')
            ->firstOrFail();

        BoardingHafalanAssessment::query()->create([
            'boarding_pencapaian_id' => $record->getKey(),
            'boarding_hafalan_point_id' => $pegonPoint->getKey(),
            'assessed_at' => now()->toDateString(),
            'score' => 85,
            'reviewer_user_id' => $admin->id,
        ]);

        BoardingHafalanAssessment::query()->create([
            'boarding_pencapaian_id' => $record->getKey(),
            'boarding_hafalan_point_id' => $cepatanPoint->getKey(),
            'assessed_at' => now()->toDateString(),
            'score' => 90,
            'reviewer_user_id' => $admin->id,
        ]);

        BoardingMaknaProgress::ensureDefaultsForPencapaian($record);

        BoardingMaknaProgress::query()
            ->where('boarding_pencapaian_id', $record->getKey())
            ->where('target_key', 'hadits_materi_materi_pegon')
            ->update(['status' => 'sebagian']);

        BoardingMaknaProgress::query()
            ->where('boarding_pencapaian_id', $record->getKey())
            ->where('target_key', 'hadits_materi_materi_cepatan')
            ->update(['status' => 'khatam']);

        BoardingBacaanAssessment::query()->create([
            'boarding_pencapaian_id' => $record->getKey(),
            'assessed_at' => now()->toDateString(),
            'pp_grade' => 'B',
            'kl_grade' => 'B',
            'tj_grade' => 'A',
            'mj_grade' => 'A',
            'reviewer_user_id' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ManageBoardingPencapaians::class)
            ->call('loadTable')
            ->assertSee('Ketercapaian')
            ->assertSee('Hafalan 2%')
            ->assertSee('Makna 3%')
            ->assertSee('Bacaan 25%')
            ->assertSee('2 / 90 materi')
            ->assertSee('Pegon Bacaan: 1 / 24 materi')
            ->assertSee('Cepatan: 1 / 23 materi')
            ->assertSee('2/56 materi')
            ->assertSee('Khatam: 1 | Sebagian: 1 | Belum diisi: 54')
            ->assertSee('1 simakan');
    }

    public function test_no_module_access_gets_forbidden_for_hafalan_page(): void
    {
        $user = User::query()->create([
            'name' => 'Guru Tanpa Akses',
            'username' => 'guru-tanpa-akses-boarding-pencapaian',
            'password' => 'secret123',
            'module_access_levels' => [
                'boarding_pencapaian' => AdminModuleAccess::NONE,
            ],
        ]);
        $user->assignRole('guru');

        $record = $this->makePencapaianRecord();

        $this->actingAs($user)
            ->get("/admin/boarding-pencapaians/{$record->getKey()}/hafalan")
            ->assertRedirect('/admin')
            ->assertSessionHas(AdminAccessDenied::FLASH_KEY);
    }

    public function test_view_access_can_load_hafalan_page_but_mutating_actions_are_forbidden(): void
    {
        $user = User::query()->create([
            'name' => 'Guru View',
            'username' => 'guru-view-boarding-pencapaian',
            'password' => 'secret123',
            'module_access_levels' => [
                'boarding_pencapaian' => AdminModuleAccess::VIEW,
            ],
        ]);
        $user->assignRole('guru');

        $record = $this->makePencapaianRecord();

        $this->actingAs($user)
            ->get("/admin/boarding-pencapaians/{$record->getKey()}/hafalan")
            ->assertOk();

        $this->actingAs($user);
        $this->assertTrue(BoardingPencapaianResource::canViewAny());
        $this->assertFalse(BoardingPencapaianResource::canEdit($record));

        $point = BoardingHafalanPoint::query()->where('is_active', true)->firstOrFail();

        Livewire::actingAs($user)
            ->test(ManageHafalan::class, ['record' => $record->getKey()])
            ->assertTableActionHidden('nilai', $point)
            ->assertTableActionHidden('reset', $point);

        $this->assertDatabaseCount('boarding_hafalan_assessments', 0);

        $this->assertFalse(BoardingHafalanPointResource::shouldRegisterNavigation());
        $this->assertFalse(BoardingHafalanPointResource::canAccess());

        $this->actingAs($user)
            ->get('/admin/boarding-hafalan-points')
            ->assertRedirect('/admin')
            ->assertSessionHas(AdminAccessDenied::FLASH_KEY);
    }

    public function test_manage_access_can_create_update_and_reset_assessment_with_latest_only_semantics(): void
    {
        $user = User::query()->create([
            'name' => 'Guru Manage',
            'username' => 'guru-manage-boarding-pencapaian',
            'password' => 'secret123',
            'module_access_levels' => [
                'boarding_pencapaian' => AdminModuleAccess::MANAGE,
            ],
        ]);
        $user->assignRole('guru');

        $record = $this->makePencapaianRecord();
        $point = BoardingHafalanPoint::query()->where('is_active', true)->firstOrFail();

        now()->setTestNow(now()->startOfDay()->addHours(8));

        Livewire::actingAs($user)
            ->test(ManageHafalan::class, ['record' => $record->getKey()])
            ->callTableAction('nilai', $point, [
                'assessed_at' => now()->toDateString(),
                'score' => 80,
                'reviewer_mode' => 'user',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseCount('boarding_hafalan_assessments', 1);

        $assessment = BoardingHafalanAssessment::query()->firstOrFail();
        $firstUpdatedAt = $assessment->updated_at;

        now()->setTestNow(now()->addSeconds(10));

        Livewire::actingAs($user)
            ->test(ManageHafalan::class, ['record' => $record->getKey()])
            ->callTableAction('nilai', $point, [
                'assessed_at' => now()->toDateString(),
                'score' => 90,
                'reviewer_mode' => 'user',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseCount('boarding_hafalan_assessments', 1);
        $assessment->refresh();

        $this->assertSame(90, $assessment->score);
        $this->assertNotEquals($firstUpdatedAt, $assessment->updated_at);

        Livewire::actingAs($user)
            ->test(ManageHafalan::class, ['record' => $record->getKey()])
            ->callTableAction('reset', $point)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseCount('boarding_hafalan_assessments', 0);

        now()->setTestNow();
    }

    public function test_inactive_point_cannot_be_created_or_updated_via_manage_action(): void
    {
        $user = User::query()->create([
            'name' => 'Guru Manage Inactive',
            'username' => 'guru-manage-inactive-boarding-pencapaian',
            'password' => 'secret123',
            'module_access_levels' => [
                'boarding_pencapaian' => AdminModuleAccess::MANAGE,
            ],
        ]);
        $user->assignRole('guru');

        $record = $this->makePencapaianRecord();

        $inactivePoint = BoardingHafalanPoint::query()->create([
            'materi_key' => 'pegon_bacaan',
            'jenis' => 'surat',
            'nama_point' => 'Point Nonaktif',
            'urutan' => 999,
            'is_active' => false,
        ]);

        Livewire::actingAs($user)
            ->test(ManageHafalan::class, ['record' => $record->getKey()])
            ->set('tableRecordsPerPage', 200)
            ->call('$refresh')
            ->tap(function ($livewire) use ($inactivePoint): void {
                $records = $livewire->instance()->getTableRecords();
                $collection = method_exists($records, 'getCollection') ? $records->getCollection() : $records;

                $this->assertFalse(
                    $collection->has($inactivePoint->getKey()),
                    'Inactive point without an assessment should not be part of the table query.'
                );
            });

        $this->assertDatabaseMissing('boarding_hafalan_assessments', [
            'boarding_pencapaian_id' => $record->getKey(),
            'boarding_hafalan_point_id' => $inactivePoint->getKey(),
        ]);

        $existing = BoardingHafalanAssessment::query()->create([
            'boarding_pencapaian_id' => $record->getKey(),
            'boarding_hafalan_point_id' => $inactivePoint->getKey(),
            'assessed_at' => now()->toDateString(),
            'score' => 50,
            'reviewer_user_id' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(ManageHafalan::class, ['record' => $record->getKey()])
            ->set('tableRecordsPerPage', 200)
            ->call('$refresh')
            ->tap(function ($livewire) use ($inactivePoint): void {
                $this->assertSame(1, $livewire->instance()->getFilteredTableQuery()->whereKey($inactivePoint->getKey())->count());

                $this->assertNotNull(
                    $livewire->instance()->getTableRecord((string) $inactivePoint->getKey()),
                    'Inactive point record should be resolvable for table actions when it is included via assessment.'
                );
            })
            ->assertTableActionHidden('nilai', $inactivePoint)
            ->assertTableActionHidden('reset', $inactivePoint);

        try {
            Livewire::actingAs($user)
                ->test(ManageHafalan::class, ['record' => $record->getKey()])
                ->callTableAction('nilai', $inactivePoint, [
                    'assessed_at' => now()->toDateString(),
                    'score' => 99,
                    'reviewer_mode' => 'user',
                ]);

            $this->fail('Inactive point mutate action should not be callable.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('action with name [nilai] is visible', $exception->getMessage());
        }

        $this->assertSame(50, $existing->fresh()->score);
    }
}




