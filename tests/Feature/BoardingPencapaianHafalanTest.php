<?php

namespace Tests\Feature;

use App\Filament\Resources\BoardingHafalanPointResource;
use App\Filament\Resources\BoardingHafalanPointResource\Pages\ManageBoardingHafalanPoints;
use App\Filament\Resources\BoardingPencapaianResource;
use App\Filament\Resources\BoardingPencapaianResource\Pages\ManageBacaan;
use App\Filament\Resources\BoardingPencapaianResource\Pages\ManageBoardingPencapaians;
use App\Filament\Resources\BoardingPencapaianResource\Pages\ManageMateriBoarding;
use App\Filament\Resources\BoardingPencapaianResource\Pages\ManageHafalan;
use App\Filament\Resources\BoardingPencapaianResource\Pages\ManageMakna;
use App\Filament\Resources\BoardingPencapaianResource\Pages\ManageMt;
use App\Models\BoardingBacaanAssessment;
use App\Models\BoardingHafalanAssessment;
use App\Models\BoardingHafalanPoint;
use App\Models\BoardingMaknaProgress;
use App\Models\BoardingMateriProgress;
use App\Models\BoardingMtProgress;
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
        $this->runBoardingMateriMigration();
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
            $table->string('kepribadian')->nullable();
            $table->string('gaya_belajar')->nullable();
            $table->string('profiling')->nullable();
            $table->string('mbti')->nullable();
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

    protected function runBoardingMateriMigration(): void
    {
        $migration = require database_path('migrations/2026_05_30_210000_expand_boarding_materi_master.php');
        $migration->up();

        $separateMigration = require database_path('migrations/2026_05_30_213000_separate_boarding_materi_tambahan_groups.php');
        $separateMigration->up();

        $consolidateMigration = require database_path('migrations/2026_05_30_214000_consolidate_boarding_materi_tambahan_class.php');
        $consolidateMigration->up();

        $splitByClassMigration = require database_path('migrations/2026_05_30_215000_split_boarding_materi_tambahan_by_class.php');
        $splitByClassMigration->up();

        $scopeMigration = require database_path('migrations/2026_05_30_216000_add_scope_and_mt_materi_boarding_points.php');
        $scopeMigration->up();

        $mtProgressMigration = require database_path('migrations/2026_05_30_217000_create_boarding_mt_progresses_table.php');
        $mtProgressMigration->up();

        if (! Schema::hasTable('boarding_makna_progresses')) {
            $maknaDanBacaanMigration = require database_path('migrations/2026_04_03_220000_create_boarding_makna_and_bacaan_tables.php');
            $maknaDanBacaanMigration->up();
        }

        $materiBoardingMigration = require database_path('migrations/2026_05_30_218000_expand_boarding_makna_and_materi_boarding.php');
        $materiBoardingMigration->up();

        $renameMaknaQuranMigration = require database_path('migrations/2026_05_30_219000_rename_boarding_makna_quran_targets.php');
        $renameMaknaQuranMigration->up();

        $materiRapotScopeMigration = require database_path('migrations/2026_05_31_080000_add_materi_rapot_scope_to_boarding_pencapaians.php');
        $materiRapotScopeMigration->up();

        $pengetesanMaknaPointMigration = require database_path('migrations/2026_05_31_090000_add_boarding_pengetesan_makna_material_point.php');
        $pengetesanMaknaPointMigration->up();
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
            ->assertTableSelectColumnHasOptions('materi_rapot_scope', BoardingPencapaian::materiRapotScopeOptions(), $record)
            ->assertSee('Ketercapaian')
            ->assertSee('Target Materi Boarding')
            ->assertSee('Hafalan 2%')
            ->assertSee('Makna 3%')
            ->assertSee('Bacaan 25%')
            ->assertSee('2 / 90 materi')
            ->assertSee('1. Kelas Pegon Bacaan : Materi Hafalan: 1 / 24 materi')
            ->assertSee('2. Kelas Lambatan : Materi Hafalan: 0 / 24 materi')
            ->assertSee('3. Kelas Cepatan : Materi Hafalan: 1 / 23 materi')
            ->assertSee('4. Kelas Materi Tambahan : Materi Hafalan: 0 / 19 materi')
            ->assertSee('2/56 materi')
            ->assertSee('Khatam: 1 | Sebagian: 1 | Belum diisi: 54')
            ->assertSee('1 simakan');

        Livewire::actingAs($admin)
            ->test(ManageBoardingPencapaians::class)
            ->call('loadTable')
            ->call('updateTableColumnState', 'materi_rapot_scope', (string) $record->getKey(), 'mt');

        $this->assertDatabaseHas('boarding_pencapaians', [
            'id' => $record->getKey(),
            'materi_rapot_scope' => 'mt',
        ]);
    }

    public function test_materi_boarding_master_includes_hafalan_and_makna_without_polluting_hafalan_assessment_page(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Materi Boarding',
            'username' => 'admin-materi-boarding',
            'password' => 'secret123',
            'module_access_levels' => [
                'boarding_pencapaian' => AdminModuleAccess::MANAGE,
            ],
        ]);
        $admin->assignRole('guru');

        $record = $this->makePencapaianRecord();
        $maknaQuran = BoardingHafalanPoint::query()
            ->where('materi_key', 'materi_tambahan_makna_quran')
            ->where('jenis', 'makna_quran')
            ->where('nama_point', "Makna Al-Qur'an Juz 1")
            ->firstOrFail();

        $this->assertSame('Materi Boarding', BoardingHafalanPointResource::getNavigationLabel());
        $this->assertSame([
            'materi_quran_bacaan' => "1. Materi Qur'an Bacaan",
            'materi_tambahan_makna_quran' => "2. Materi Qur'an Makna",
            'materi_tambahan_makna_hadits' => '3. Materi Hadits Makna',
            'materi_pengetesan_makna' => '4. Materi Pengetesan Makna',
            'pegon_bacaan' => '5. Materi Hafalan - 1. Hafalan Kelas Pegon Bacaan',
            'lambatan' => '5. Materi Hafalan - 2. Hafalan Kelas Lambatan',
            'cepatan' => '5. Materi Hafalan - 3. Hafalan Kelas Cepatan',
            'materi_tambahan_hafalan' => '5. Materi Hafalan - 4. Hafalan Materi Tambahan',
        ], BoardingHafalanPoint::MATERI_OPTIONS);
        $this->assertSame(
            "2. Materi Qur'an Makna",
            BoardingHafalanPoint::materiKeyOptions()['materi_tambahan_makna_quran']
        );
        $this->assertSame("Makna Qur'an", BoardingHafalanPoint::jenisOptions()['makna_quran']);
        $this->assertSame('Pengetesan Makna', BoardingHafalanPoint::jenisOptions()['pengetesan_makna']);
        $this->assertDatabaseHas('boarding_hafalan_points', [
            'materi_scope' => 'boarding',
            'materi_key' => 'materi_quran_bacaan',
            'jenis' => 'bacaan_quran',
            'nama_point' => "Bacaan Qur'an",
        ]);
        $this->assertDatabaseHas('boarding_hafalan_points', [
            'materi_scope' => 'boarding',
            'materi_key' => 'materi_pengetesan_makna',
            'jenis' => 'pengetesan_makna',
            'nama_point' => 'Pengetesan Makna',
        ]);
        $this->assertDatabaseHas('boarding_hafalan_points', [
            'materi_scope' => 'mt',
            'materi_key' => 'mt_makna_hadits',
            'jenis' => 'mt_makna_hadits',
            'nama_point' => 'Muslim Jilid 1',
        ]);
        $this->assertDatabaseHas('boarding_hafalan_points', [
            'materi_scope' => 'mt',
            'materi_key' => 'mt_catatan_saran',
            'jenis' => 'mt_catatan_saran',
            'nama_point' => 'Kesemangatan',
        ]);
        $this->assertDatabaseHas('boarding_hafalan_points', [
            'materi_key' => 'materi_tambahan_hafalan',
            'jenis' => 'doa',
            'nama_point' => 'Doa Sholat Hajat',
        ]);
        $this->assertDatabaseHas('boarding_hafalan_points', [
            'materi_key' => 'materi_tambahan_hafalan',
            'jenis' => 'doa',
            'nama_point' => 'Doa Sholat Jenazah',
        ]);
        $this->assertDatabaseHas('boarding_hafalan_points', [
            'materi_key' => 'materi_tambahan_hafalan',
            'jenis' => 'doa',
            'nama_point' => 'Doa PR 13 dan keutamaannya',
        ]);
        $this->assertDatabaseHas('boarding_hafalan_points', [
            'materi_key' => 'materi_tambahan_hafalan',
            'jenis' => 'surat',
            'nama_point' => "An-Naba'",
        ]);
        $this->assertDatabaseHas('boarding_hafalan_points', [
            'materi_key' => 'materi_tambahan_hafalan',
            'jenis' => 'surat',
            'nama_point' => 'Al-Lail',
        ]);
        $this->assertDatabaseHas('boarding_hafalan_points', [
            'materi_key' => 'materi_tambahan_makna_hadits',
            'jenis' => 'makna_hadits',
            'nama_point' => 'K. Sholah',
        ]);
        $this->assertDatabaseMissing('boarding_hafalan_points', [
            'materi_key' => 'seleksi_saringan',
        ]);
        $this->assertDatabaseMissing('boarding_hafalan_points', [
            'materi_key' => 'materi_tambahan',
            'jenis' => 'makna_quran',
        ]);

        Livewire::actingAs($admin)
            ->test(ManageHafalan::class, ['record' => $record->getKey()])
            ->set('tableRecordsPerPage', 200)
            ->call('$refresh')
            ->assertSee('Menu Materi Boarding')
            ->assertSee("Materi Qur'an")
            ->assertSee('Bacaan')
            ->assertSee('Makna')
            ->assertSee('Hadits')
            ->assertSee('Pengetesan')
            ->assertSee('Hafalan')
            ->assertSeeHtml("boarding-pencapaians/{$record->getKey()}/materi")
            ->tap(function ($livewire) use ($maknaQuran): void {
                $table = $livewire->instance()->getTable();
                $this->assertSame(200, $table->getDefaultPaginationPageOption());
                $this->assertSame([25, 50, 100, 200], $table->getPaginationPageOptions());
                $this->assertTrue($table->areGroupsCollapsedByDefault());
                $this->assertTrue($table->getGroups()['materi_key']->isCollapsible());

                $records = $livewire->instance()->getTableRecords();
                $collection = method_exists($records, 'getCollection') ? $records->getCollection() : $records;
                $materiKeys = $collection->pluck('materi_key')->unique()->values()->all();
                $expectedMateriKeys = array_values(array_filter(
                    array_keys(BoardingHafalanPoint::MATERI_OPTIONS),
                    fn (string $materiKey): bool => in_array($materiKey, $materiKeys, true),
                ));

                $this->assertSame('pegon_bacaan', $materiKeys[0] ?? null);
                $this->assertSame($expectedMateriKeys, $materiKeys);

                $this->assertFalse(
                    $collection->has($maknaQuran->getKey()),
                    'Materi makna Quran tidak boleh muncul di halaman penilaian hafalan.'
                );
            });
    }

    public function test_materi_boarding_table_can_be_edited_inline_like_a_simple_sheet(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Materi Inline',
            'username' => 'admin-materi-inline',
            'password' => 'secret123',
            'module_access_levels' => [
                'boarding_pencapaian' => AdminModuleAccess::MANAGE,
            ],
        ]);
        $admin->assignRole('guru');

        $point = BoardingHafalanPoint::query()
            ->where('materi_key', 'pegon_bacaan')
            ->where('jenis', 'surat')
            ->orderBy('urutan')
            ->firstOrFail();

        Livewire::actingAs($admin)
            ->test(ManageBoardingHafalanPoints::class)
            ->set('tableRecordsPerPage', 200)
            ->call('loadTable')
            ->tap(function ($livewire): void {
                $records = $livewire->instance()->getTableRecords();
                $collection = method_exists($records, 'getCollection') ? $records->getCollection() : $records;
                $materiKeys = $collection->pluck('materi_key')->unique()->values()->all();
                $expected = [
                    'materi_quran_bacaan',
                    'materi_tambahan_makna_quran',
                    'materi_tambahan_makna_hadits',
                    'materi_pengetesan_makna',
                    'pegon_bacaan',
                    'lambatan',
                    'cepatan',
                    'materi_tambahan_hafalan',
                ];

                $this->assertSame($expected, array_values(array_filter(
                    $materiKeys,
                    fn (string $materiKey): bool => in_array($materiKey, $expected, true),
                )));
            });

        Livewire::actingAs($admin)
            ->test(ManageBoardingHafalanPoints::class)
            ->set('activeTab', 'mt')
            ->call('loadTable')
            ->tap(function ($livewire): void {
                $records = $livewire->instance()->getTableRecords();
                $collection = method_exists($records, 'getCollection') ? $records->getCollection() : $records;

                $this->assertNotEmpty($collection);
                $this->assertTrue(
                    $collection->every(fn (BoardingHafalanPoint $record): bool => $record->materi_scope === 'mt'),
                    'Tab Materi MT harus hanya menampilkan materi_scope MT.'
                );
            });

        Livewire::actingAs($admin)
            ->test(ManageBoardingHafalanPoints::class)
            ->call('loadTable')
            ->assertTableFilterExists('materi_key', function ($filter): bool {
                return $filter->getLabel() === 'Materi :'
                    && $filter->getPlaceholder() === 'Semua materi';
            })
            ->filterTable('materi_key', 'materi_tambahan_makna_quran')
            ->tap(function ($livewire): void {
                $records = $livewire->instance()->getTableRecords();
                $collection = method_exists($records, 'getCollection') ? $records->getCollection() : $records;

                $this->assertNotEmpty($collection);
                $this->assertTrue(
                    $collection->every(fn (BoardingHafalanPoint $record): bool => $record->materi_key === 'materi_tambahan_makna_quran'),
                    'Filter Pilih Materi Kelas harus hanya menampilkan kelas yang dipilih.'
                );
            });

        Livewire::actingAs($admin)
            ->test(ManageBoardingHafalanPoints::class)
            ->set('activeTab', 'mt')
            ->filterTable('materi_key', 'mt_catatan_saran')
            ->call('loadTable')
            ->tap(function ($livewire): void {
                $records = $livewire->instance()->getTableRecords();
                $collection = method_exists($records, 'getCollection') ? $records->getCollection() : $records;

                $this->assertNotEmpty($collection);
                $this->assertTrue(
                    $collection->every(fn (BoardingHafalanPoint $record): bool => $record->materi_scope === 'mt'
                        && $record->materi_key === 'mt_catatan_saran'),
                    'Filter Materi harus bisa menampilkan satu kelompok MT saja, misalnya Catatan dan Saran.'
                );
            })
            ->assertSee('Kedisiplinan')
            ->assertDontSee('Muslim Jilid 1');

        Livewire::actingAs($admin)
            ->test(ManageBoardingHafalanPoints::class)
            ->resetTableFilters()
            ->call('loadTable')
            ->assertTableSelectColumnHasOptions('materi_key', BoardingHafalanPoint::allMateriOptions(), $point)
            ->assertTableSelectColumnHasOptions('jenis', BoardingHafalanPoint::jenisOptions(), $point)
            ->call('updateTableColumnState', 'materi_key', (string) $point->getKey(), 'lambatan')
            ->call('updateTableColumnState', 'jenis', (string) $point->getKey(), 'doa')
            ->call('updateTableColumnState', 'nama_point', (string) $point->getKey(), 'Materi Inline Test')
            ->call('updateTableColumnState', 'urutan', (string) $point->getKey(), 99)
            ->call('updateTableColumnState', 'is_active', (string) $point->getKey(), false);

        $point->refresh();

        $this->assertSame('lambatan', $point->materi_key);
        $this->assertSame('doa', $point->jenis);
        $this->assertSame('Materi Inline Test', $point->nama_point);
        $this->assertSame(99, (int) $point->urutan);
        $this->assertFalse($point->is_active);
    }

    public function test_mt_progress_page_uses_requested_targets_and_input_types(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin MT Progress',
            'username' => 'admin-mt-progress',
            'password' => 'secret123',
            'module_access_levels' => [
                'boarding_pencapaian' => AdminModuleAccess::MANAGE,
            ],
        ]);
        $admin->assignRole('guru');

        $record = $this->makePencapaianRecord();
        $record->update([
            'materi_rapot_scope' => BoardingPencapaian::MATERI_RAPOT_SCOPE_MT,
        ]);

        Livewire::actingAs($admin)
            ->test(ManageMt::class, ['record' => $record->getKey()])
            ->call('loadTable')
            ->assertSee('Muslim Jilid 1')
            ->assertSee('Tugas Praktek')
            ->assertSee('Hafalan Surat Quran Juz 1')
            ->assertSee('Tabel Materi MT')
            ->assertDontSee('Materi Boarding')
            ->assertSee('Catatan')
            ->assertSee('Kedisiplinan');

        $this->assertSame(11, BoardingMtProgress::query()
            ->where('boarding_pencapaian_id', $record->getKey())
            ->count());

        $muslim = BoardingMtProgress::query()
            ->where('boarding_pencapaian_id', $record->getKey())
            ->where('target_key', 'muslim_jilid_1')
            ->firstOrFail();

        $praktek = BoardingMtProgress::query()
            ->where('boarding_pencapaian_id', $record->getKey())
            ->where('target_key', 'tugas_praktek')
            ->firstOrFail();

        Livewire::actingAs($admin)
            ->test(ManageMt::class, ['record' => $record->getKey()])
            ->call('loadTable')
            ->callTableAction('ubah', $muslim, [
                'progress_value' => 12,
                'target_total' => 20,
                'notes' => 'Khatam sebagian.',
            ])
            ->callTableAction('ubah', $praktek, [
                'grade' => 'baik',
                'notes' => 'Praktek baik.',
            ]);

        $this->assertDatabaseHas('boarding_mt_progresses', [
            'id' => $muslim->getKey(),
            'progress_value' => 12,
            'target_total' => 20,
            'unit_label' => 'lembar',
        ]);
        $this->assertDatabaseHas('boarding_mt_progresses', [
            'id' => $praktek->getKey(),
            'grade' => 'baik',
            'notes' => 'Praktek baik.',
        ]);
    }

    public function test_materi_boarding_recap_links_quran_hadits_hafalan_and_manual_grades(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Materi Boarding Recap',
            'username' => 'admin-materi-boarding-recap',
            'password' => 'secret123',
            'module_access_levels' => [
                'boarding_pencapaian' => AdminModuleAccess::MANAGE,
            ],
        ]);
        $admin->assignRole('guru');

        $record = $this->makePencapaianRecord();

        BoardingMaknaProgress::ensureDefaultsForPencapaian($record);

        $quranJuzOne = BoardingMaknaProgress::query()
            ->where('boarding_pencapaian_id', $record->getKey())
            ->where('target_key', 'quran_juz_1')
            ->firstOrFail();

        Livewire::actingAs($admin)
            ->test(ManageMakna::class, ['record' => $record->getKey()])
            ->call('loadTable')
            ->assertSee('Menu Materi Boarding')
            ->assertSee("Materi Qur'an")
            ->assertSee('Bacaan')
            ->assertSee('Makna')
            ->assertSee('Hadits')
            ->assertSee('Pengetesan')
            ->assertSee('Hafalan')
            ->assertSeeHtml("boarding-pencapaians/{$record->getKey()}/materi")
            ->assertSee('Tabel Makna')
            ->assertSee('Kurang')
            ->assertSee('Dari')
            ->tap(function ($livewire): void {
                $table = $livewire->instance()->getTable();
                $this->assertSame(200, $table->getDefaultPaginationPageOption());
                $this->assertSame([25, 50, 100, 200], $table->getPaginationPageOptions());
                $this->assertTrue($table->areGroupsCollapsedByDefault());
                $this->assertTrue($table->getGroups()['target_group']->isCollapsible());

                $records = $livewire->instance()->getTableRecords();
                $collection = method_exists($records, 'getCollection') ? $records->getCollection() : $records;
                $targetGroups = $collection->pluck('target_group')->unique()->values()->all();
                $expectedGroups = array_values(array_filter(
                    array_keys(BoardingMaknaProgress::GROUP_OPTIONS),
                    fn (string $group): bool => in_array($group, $targetGroups, true),
                ));

                $this->assertSame('quran', $targetGroups[0] ?? null);
                $this->assertSame($expectedGroups, $targetGroups);
            })
            ->callTableAction('ubah', $quranJuzOne, [
                'status' => 'sebagian',
                'remaining_pages' => 3,
                'total_pages' => 20,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('boarding_makna_progresses', [
            'id' => $quranJuzOne->getKey(),
            'status' => 'sebagian',
            'remaining_pages' => 3,
            'total_pages' => 20,
        ]);

        $pegonPoints = BoardingHafalanPoint::query()
            ->where('materi_key', 'pegon_bacaan')
            ->where('is_active', true)
            ->whereIn('jenis', BoardingHafalanPoint::hafalanJenis())
            ->get();

        $this->assertNotEmpty($pegonPoints);

        foreach ($pegonPoints as $point) {
            BoardingHafalanAssessment::query()->create([
                'boarding_pencapaian_id' => $record->getKey(),
                'boarding_hafalan_point_id' => $point->getKey(),
                'assessed_at' => now()->toDateString(),
                'score' => 90,
                'reviewer_user_id' => $admin->id,
            ]);
        }

        $bacaanAssessment = BoardingBacaanAssessment::query()->create([
            'boarding_pencapaian_id' => $record->getKey(),
            'assessed_at' => now()->toDateString(),
            'pp_grade' => 'A',
            'kl_grade' => 'A',
            'tj_grade' => 'B',
            'mj_grade' => 'B',
            'reviewer_user_id' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ManageBacaan::class, ['record' => $record->getKey()])
            ->call('loadTable')
            ->assertSee('Menu Materi Boarding')
            ->assertSee("Materi Qur'an")
            ->assertSee('Bacaan')
            ->assertSee('Makna')
            ->assertSee('Hadits')
            ->assertSee('Pengetesan')
            ->assertSee('Hafalan')
            ->assertSeeHtml("boarding-pencapaians/{$record->getKey()}/materi")
            ->assertSee('Tabel Bacaan')
            ->assertSee('PP')
            ->assertSee('KL')
            ->call('updateTableColumnState', 'notes', (string) $bacaanAssessment->getKey(), 'Bacaan dicek dari tabel.');

        $this->assertDatabaseHas('boarding_bacaan_assessments', [
            'id' => $bacaanAssessment->getKey(),
            'notes' => 'Bacaan dicek dari tabel.',
        ]);

        BoardingMateriProgress::ensureDefaultsForPencapaian($record);

        $pengetesan = BoardingMateriProgress::query()
            ->where('boarding_pencapaian_id', $record->getKey())
            ->where('target_key', 'pengetesan_makna')
            ->firstOrFail();

        Livewire::actingAs($admin)
            ->test(ManageMateriBoarding::class, ['record' => $record->getKey()])
            ->call('loadTable')
            ->assertSee('Menu Materi Boarding')
            ->assertDontSee('Materi MT')
            ->assertSee('Input Cepat')
            ->assertSee("Materi Qur'an")
            ->assertSee('Bacaan')
            ->assertSee('Makna')
            ->assertSee('Hadits')
            ->assertSee('Pengetesan')
            ->assertSee('Hafalan')
            ->assertSee('Pengetesan Makna')
            ->assertSee('Kedisiplinan')
            ->assertSee('Catatan')
            ->assertSee('Baik')
            ->callTableAction('ubah', $pengetesan, [
                'grade' => 'cukup',
                'notes' => 'Perlu penguatan makna.',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('boarding_materi_progresses', [
            'id' => $pengetesan->getKey(),
            'grade' => 'cukup',
            'notes' => 'Perlu penguatan makna.',
        ]);
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




