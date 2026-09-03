<?php

namespace Tests\Feature;

use App\Exports\GuruTendikExport;
use App\Exports\GuruTendikImportTemplateExport;
use App\Exports\UksRecordExport;
use App\Exports\UksRecordImportTemplateExport;
use App\Filament\Resources\BerkasGuruResource;
use App\Filament\Resources\DataSiswaResource;
use App\Filament\Resources\DataSiswaResource\Pages\ManageDataSiswas;
use App\Filament\Resources\GuruTendikResource;
use App\Filament\Resources\GuruTendikResource\Pages\ListGuruTendiks;
use App\Filament\Resources\PrestasiResource;
use App\Filament\Resources\UserResource;
use App\Filament\Widgets\GuruTendikAccountStatsOverview;
use App\Filament\Widgets\GuruTendikGenderChart;
use App\Filament\Widgets\GuruTendikJenisPtkChart;
use App\Filament\Widgets\GuruTendikStatsOverview;
use App\Filament\Widgets\PrestasiByRombelChart;
use App\Filament\Widgets\PrestasiStatsOverview;
use App\Filament\Widgets\UksCategoryChart;
use App\Filament\Widgets\UksMeasurementChart;
use App\Filament\Widgets\UksStatsOverview;
use App\Models\BerkasGuru;
use App\Models\DataSiswa;
use App\Models\GuruTendik;
use App\Models\JenisBerkas;
use App\Models\Prestasi;
use App\Models\UksRecord;
use App\Models\User;
use App\Support\Admin\AdminModuleAccess;
use App\Support\Admin\AdminRoleTemplateSupport;
use App\Support\GuruTendik\GuruTendikAccountProvisioner;
use App\Support\GuruTendik\GuruTendikWorkbookImporter;
use App\Support\Uks\UksRecordWorkbookImporter;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\Feature\Concerns\BootstrapsAdminFeatureTables;
use Tests\TestCase;

class GuruModulesAndUksTest extends TestCase
{
    use BootstrapsAdminFeatureTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapAdminFeatureTables();
    }

    public function test_guru_only_sees_own_profile_and_private_documents(): void
    {
        $jenisBerkas = JenisBerkas::query()->create([
            'nama_berkas' => 'Sertifikat Guru',
            'wajib' => 'ya',
            'urutan' => 1,
            'status' => 'aktif',
        ]);

        $guruSendiri = GuruTendik::query()->create([
            'nama' => 'Ustadz Hafid',
            'nip' => '1987001',
            'jenis_ptk' => 'Guru Mapel',
            'status' => 'aktif',
        ]);

        $guruLain = GuruTendik::query()->create([
            'nama' => 'Ustadz Rian',
            'nip' => '1987002',
            'jenis_ptk' => 'Guru Mapel',
            'status' => 'aktif',
        ]);

        Storage::disk('public')->put('berkas_guru/hafid.pdf', 'dummy-pdf');
        Storage::disk('public')->put('berkas_guru/rian.pdf', 'dummy-pdf');

        $berkasSendiri = BerkasGuru::query()->create([
            'guru_id' => $guruSendiri->id,
            'jenis_berkas_id' => $jenisBerkas->id,
            'file_path' => 'berkas_guru/hafid.pdf',
            'keterangan' => 'Dokumen pribadi Hafid',
            'uploaded_at' => now(),
            'has_deleted' => 0,
        ]);

        $berkasGuruLain = BerkasGuru::query()->create([
            'guru_id' => $guruLain->id,
            'jenis_berkas_id' => $jenisBerkas->id,
            'file_path' => 'berkas_guru/rian.pdf',
            'keterangan' => 'Dokumen pribadi Rian',
            'uploaded_at' => now(),
            'has_deleted' => 0,
        ]);

        $guruUser = User::query()->create([
            'name' => 'Hafid',
            'username' => 'hafid',
            'password' => 'secret123',
            'guru_tendik_id' => $guruSendiri->id,
            'guru_mapel_label' => 'Bahasa Arab',
            'guru_walas_scope' => ['X.I / 2025-2026'],
        ]);
        $guruUser->assignRole('guru');

        $this->actingAs($guruUser);

        $this->assertSame(['Dashboard', 'Manajemen Sekolah'], $guruUser->resolvedNavigationGroups());
        $this->assertSame([$guruSendiri->id], GuruTendikResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$berkasSendiri->id], BerkasGuruResource::getEloquentQuery()->pluck('id')->all());

        $this->get(route('admin.berkas-gurus.preview', $berkasSendiri))
            ->assertOk()
            ->assertSee('Preview Berkas Guru')
            ->assertSee('Ustadz Hafid')
            ->assertSee(route('admin.berkas-gurus.content', $berkasSendiri), false);

        $contentResponse = $this->get(route('admin.berkas-gurus.content', $berkasSendiri));
        $contentResponse
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $contentResponse->headers->get('Cache-Control'));

        $this->get(route('admin.berkas-gurus.preview', $berkasGuruLain))
            ->assertNotFound();

        $this->get(route('admin.berkas-gurus.content', $berkasGuruLain))
            ->assertNotFound();
    }

    public function test_guru_admin_role_gets_full_admin_access_without_teacher_scope(): void
    {
        $guruSendiri = GuruTendik::query()->create([
            'nama' => 'Ustadz Full Admin',
            'nip' => '1987011',
            'jenis_ptk' => 'Guru Mapel',
            'status' => 'aktif',
        ]);

        $guruLain = GuruTendik::query()->create([
            'nama' => 'Ustadz Lain',
            'nip' => '1987012',
            'jenis_ptk' => 'Guru Mapel',
            'status' => 'aktif',
        ]);

        $siswaPutra = DataSiswa::query()->create([
            'nama' => 'Santri Putra',
            'rombel_saat_ini' => 'X.I / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $siswaPutri = DataSiswa::query()->create([
            'nama' => 'Santri Putri',
            'rombel_saat_ini' => 'X.A / 2025-2026',
            'jk' => 'P',
            'status' => 'aktif',
        ]);

        $guruAdmin = User::query()->create([
            'name' => 'Guru Admin',
            'username' => 'guru-admin',
            'password' => 'secret123',
            'guru_tendik_id' => $guruSendiri->id,
            'guru_walas_scope' => ['X.I / 2025-2026'],
        ]);
        $guruAdmin->assignRole(['guru', 'guru_admin']);

        $this->actingAs($guruAdmin);
        $guruAdmin->refresh()->loadMissing('roles');

        $this->assertTrue($guruAdmin->hasFullAdminAccess());
        $this->assertSame(array_keys(User::navigationGroupOptions()), $guruAdmin->resolvedNavigationGroups());
        $this->assertSame([], $guruAdmin->resolvedNavigationItems());
        $this->assertSame(AdminModuleAccess::MANAGE, $guruAdmin->moduleAccessLevel('users'));
        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(GuruTendikResource::canCreate());
        $this->assertTrue(BerkasGuruResource::canCreate());

        $visibleGuruIds = GuruTendikResource::getEloquentQuery()->pluck('id')->all();
        sort($visibleGuruIds);
        $expectedGuruIds = [$guruSendiri->id, $guruLain->id];
        sort($expectedGuruIds);

        $visibleSiswaIds = DataSiswa::applyVisibleScope(DataSiswa::query(), $guruAdmin)->pluck('id')->all();
        sort($visibleSiswaIds);
        $expectedSiswaIds = [$siswaPutra->id, $siswaPutri->id];
        sort($expectedSiswaIds);

        $this->assertSame($expectedGuruIds, $visibleGuruIds);
        $this->assertSame($expectedSiswaIds, $visibleSiswaIds);
    }

    public function test_guru_walas_preset_permission_syncs_student_modules_and_scope_automatically(): void
    {
        $guru = GuruTendik::query()->create([
            'nama' => 'Ustadz Zaid',
            'nip' => '1987003',
            'jenis_ptk' => 'Wali Kelas',
            'status' => 'aktif',
        ]);

        $siswaScope = DataSiswa::query()->create([
            'nama' => 'Hafid',
            'rombel_saat_ini' => 'X.I / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $siswaLuarScope = DataSiswa::query()->create([
            'nama' => 'Rian',
            'rombel_saat_ini' => 'XI.I / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $prestasiScope = Prestasi::query()->create([
            'siswa_id' => $siswaScope->id,
            'nama_lomba' => 'Olimpiade Matematika',
            'tanggal_prestasi' => now()->toDateString(),
            'penyelenggara' => 'MKKS',
            'juara' => 'Juara 1',
        ]);

        $prestasiLuarScope = Prestasi::query()->create([
            'siswa_id' => $siswaLuarScope->id,
            'nama_lomba' => 'Lomba Cerdas Cermat',
            'tanggal_prestasi' => now()->toDateString(),
            'penyelenggara' => 'Forum Sekolah',
            'juara' => 'Juara 2',
        ]);

        $guruUser = User::query()->create([
            'name' => 'Zaid',
            'username' => 'zaid',
            'password' => 'secret123',
            'guru_tendik_id' => $guru->id,
            'guru_walas_scope' => ['X.I / 2025-2026'],
            'allowed_navigation_groups' => ['Guru/Tendik'],
            'allowed_navigation_items' => [GuruTendikResource::class, BerkasGuruResource::class],
        ]);
        $guruUser->assignRole('guru');

        UserResource::syncScopedModuleConfiguration($guruUser, [
            'roles' => $guruUser->roles->pluck('id')->all(),
            'module_access_levels' => [
                'guru_tendik' => AdminModuleAccess::MANAGE,
                'berkas_guru' => AdminModuleAccess::MANAGE,
                'data_siswa' => AdminModuleAccess::VIEW,
                'prestasi' => AdminModuleAccess::MANAGE,
            ],
        ]);

        $guruUser->refresh();

        $this->assertSame(AdminModuleAccess::VIEW, $guruUser->moduleAccessLevel('data_siswa'));
        $this->assertSame(AdminModuleAccess::MANAGE, $guruUser->moduleAccessLevel('prestasi'));
        $this->assertContains('Manajemen Sekolah', $guruUser->resolvedNavigationGroups());
        $this->assertContains(DataSiswaResource::class, $guruUser->resolvedNavigationItems());
        $this->assertContains(PrestasiResource::class, $guruUser->resolvedNavigationItems());

        $this->actingAs($guruUser);

        $this->assertEqualsCanonicalizing([$siswaScope->id, $siswaLuarScope->id], DataSiswaResource::getEloquentQuery()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$prestasiScope->id, $prestasiLuarScope->id], PrestasiResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_manual_guru_permission_selection_is_preserved_by_scope_sync(): void
    {
        $guru = GuruTendik::query()->create([
            'nama' => 'Ustadz Salman',
            'nip' => '1987004',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
        ]);

        $guruUser = User::query()->create([
            'name' => 'Salman',
            'username' => 'salman',
            'password' => 'secret123',
            'guru_tendik_id' => $guru->id,
            'allowed_navigation_groups' => ['Guru/Tendik'],
            'allowed_navigation_items' => [GuruTendikResource::class, BerkasGuruResource::class],
        ]);
        $guruUser->assignRole('guru');
        $guruUser->syncPermissions(['users.view']);

        UserResource::syncScopedModuleConfiguration($guruUser, [
            'roles' => $guruUser->roles->pluck('id')->all(),
            'module_access_levels' => [
                'guru_tendik' => AdminModuleAccess::VIEW,
                'berkas_guru' => AdminModuleAccess::VIEW,
                'data_siswa' => AdminModuleAccess::VIEW,
            ],
        ]);

        $guruUser->refresh();

        $this->assertTrue($guruUser->canViewModule('data_siswa'));
        $this->assertContains('Manajemen Sekolah', $guruUser->resolvedNavigationGroups());
        $this->assertContains(DataSiswaResource::class, $guruUser->resolvedNavigationItems());
    }

    public function test_copy_guru_access_keeps_target_profile_link_but_copies_access_settings(): void
    {
        $guruSumber = GuruTendik::query()->create([
            'nama' => 'Ustadz Sumber',
            'nip' => '1987005',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
        ]);

        $guruTarget = GuruTendik::query()->create([
            'nama' => 'Ustadz Target',
            'nip' => '1987006',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
        ]);

        $sourceUser = User::query()->create([
            'name' => 'Sumber',
            'username' => 'sumber',
            'password' => 'secret123',
            'guru_tendik_id' => $guruSumber->id,
            'guru_walas_scope' => ['X.I / 2025-2026'],
            'allowed_navigation_groups' => ['Guru/Tendik', 'Siswa'],
            'allowed_navigation_items' => [GuruTendikResource::class, BerkasGuruResource::class, DataSiswaResource::class],
        ]);
        $sourceUser->assignRole('guru');
        $sourceUser->syncPermissions(['users.view']);

        UserResource::syncScopedModuleConfiguration($sourceUser, [
            'roles' => $sourceUser->roles->pluck('id')->all(),
            'module_access_levels' => [
                'guru_tendik' => AdminModuleAccess::VIEW,
                'berkas_guru' => AdminModuleAccess::VIEW,
                'data_siswa' => AdminModuleAccess::VIEW,
            ],
        ]);

        $targetUser = User::query()->create([
            'name' => 'Target',
            'username' => 'target',
            'password' => 'secret123',
            'guru_tendik_id' => $guruTarget->id,
            'guru_walas_scope' => ['XI.I / 2025-2026'],
            'allowed_navigation_groups' => ['Guru/Tendik'],
            'allowed_navigation_items' => [GuruTendikResource::class],
        ]);
        $targetUser->assignRole('guru');
        $targetUser->syncPermissions(['users.view']);

        UserResource::copyGuruAccess($sourceUser, $targetUser);

        $targetUser->refresh();

        $this->assertSame($guruTarget->id, $targetUser->guru_tendik_id);
        $this->assertSame(['XI.I / 2025-2026'], $targetUser->guruWalasScopes());
        $this->assertTrue($targetUser->canViewModule('data_siswa'));
        $this->assertTrue($targetUser->canViewModule('users'));
        $this->assertContains('Manajemen Sekolah', $targetUser->resolvedNavigationGroups());
        $this->assertContains(DataSiswaResource::class, $targetUser->resolvedNavigationItems());
    }

    public function test_view_only_module_access_blocks_destructive_actions(): void
    {
        $guru = GuruTendik::query()->create([
            'nama' => 'Ustadz View Only',
            'nip' => '1987011',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
        ]);

        $guruUser = User::query()->create([
            'name' => 'View Only',
            'username' => 'view-only-guru',
            'password' => 'secret123',
            'guru_tendik_id' => $guru->id,
        ]);
        $guruUser->assignRole('guru');

        UserResource::syncScopedModuleConfiguration($guruUser, [
            'roles' => $guruUser->roles->pluck('id')->all(),
            'module_access_levels' => [
                'guru_tendik' => AdminModuleAccess::VIEW,
                'berkas_guru' => AdminModuleAccess::VIEW,
                'data_siswa' => AdminModuleAccess::VIEW,
                'prestasi' => AdminModuleAccess::VIEW,
            ],
        ]);

        $siswa = DataSiswa::query()->create([
            'nama' => 'Ammar',
            'rombel_saat_ini' => 'X.I / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);
        $prestasi = Prestasi::query()->create([
            'siswa_id' => $siswa->id,
            'nama_lomba' => 'Lomba IPA',
            'tanggal_prestasi' => now()->toDateString(),
            'penyelenggara' => 'Sekolah',
            'juara' => 'Juara 1',
        ]);

        $this->actingAs($guruUser);

        $this->assertFalse(DataSiswaResource::canCreate());
        $this->assertFalse(PrestasiResource::canCreate());
        $this->assertFalse(GuruTendikResource::canCreate());
        $this->assertFalse(DataSiswaResource::canDelete($siswa));
        $this->assertFalse(PrestasiResource::canDelete($prestasi));
        $this->assertFalse(UserResource::canDelete($guruUser));
    }

    #[RunInSeparateProcess]
    public function test_manage_data_siswa_page_can_apply_chart_filters_without_full_navigation(): void
    {
        DataSiswa::query()->create([
            'nama' => 'Abiel',
            'rombel_saat_ini' => 'X.I / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $admin = User::query()->create([
            'name' => 'Admin Filter Siswa',
            'username' => 'admin-filter-siswa',
            'password' => 'secret123',
        ]);
        $admin->assignRole('admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(ManageDataSiswas::class)
            ->call('applyChartFiltersFromWidget', [
                'status' => ['value' => 'keluar'],
                'jk' => ['value' => 'L'],
                'rombel_saat_ini' => ['value' => 'X.I / 2025-2026'],
            ], [
                'chart_status' => 'keluar',
                'chart_jk' => 'L',
                'chart_rombel' => 'X.I / 2025-2026',
            ])
            ->assertSet('tableFilters.status.value', 'keluar')
            ->assertSet('tableFilters.jk.value', 'L')
            ->assertSet('tableFilters.rombel_saat_ini.value', 'X.I / 2025-2026');
    }

    public function test_prestasi_logs_history_and_widgets_summarize_visible_data(): void
    {
        $siswaA = DataSiswa::query()->create([
            'nama' => 'Abiel',
            'rombel_saat_ini' => 'X.I / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $siswaB = DataSiswa::query()->create([
            'nama' => 'Siti',
            'rombel_saat_ini' => 'XI.I / 2025-2026',
            'jk' => 'P',
            'status' => 'aktif',
        ]);

        $prestasiA = Prestasi::query()->create([
            'siswa_id' => $siswaA->id,
            'nama_lomba' => 'Olimpiade Sains Kota',
            'tanggal_prestasi' => now()->toDateString(),
            'penyelenggara' => 'Dinas Pendidikan',
            'juara' => 'Juara 1',
            'hadiah' => 'Trofi dan piagam',
        ]);

        Prestasi::query()->create([
            'siswa_id' => $siswaB->id,
            'nama_lomba' => 'Lomba Pidato',
            'tanggal_prestasi' => now()->subMonth()->toDateString(),
            'penyelenggara' => 'Forum Bahasa',
            'juara' => 'Juara 2',
            'hadiah' => 'Piagam',
        ]);

        $prestasiA->update([
            'hadiah' => 'Trofi, piagam, dan uang pembinaan',
        ]);

        $this->assertCount(2, $prestasiA->fresh()->histories);

        $statsWidget = new class extends PrestasiStatsOverview
        {
            public function exposeStats(): array
            {
                return $this->getStats();
            }
        };

        $chartWidget = new class extends PrestasiByRombelChart
        {
            public function exposeData(): array
            {
                return $this->getData();
            }
        };

        $stats = collect($statsWidget->exposeStats())
            ->mapWithKeys(fn (Stat $stat): array => [(string) $stat->getLabel() => (string) $stat->getValue()])
            ->all();
        $chart = $chartWidget->exposeData();

        $this->assertSame('2', $stats['Total Prestasi']);
        $this->assertSame('2', $stats['Siswa Berprestasi']);
        $this->assertSame('1', $stats['Juara 1 / Setara']);
        $this->assertSame(['X.I / 2025-2026', 'XI.I / 2025-2026'], $chart['labels']);
        $this->assertSame([1, 1], $chart['datasets'][0]['data']);
    }

    public function test_uks_import_export_and_widgets_support_anthropometry_fields(): void
    {
        $path = $this->createWorkbook([
            ['nama_siswa', 'kelas', 'tanggal_sakit', 'kategori', 'penanganan', 'berat_badan', 'tinggi_badan', 'lingkar_kepala', 'catatan'],
            ['Abiel', 'X.I / 2025-2026', now()->toDateString(), 'Demam', 'Observasi UKS', 45.5, 162, 53, 'Perlu istirahat.'],
            ['Abiel', 'X.I / 2025-2026', now()->toDateString(), 'Demam', 'Observasi UKS lanjutan', 45.5, 162, 53, 'Data diperbarui.'],
        ]);

        try {
            $result = app(UksRecordWorkbookImporter::class)->import($path);
        } finally {
            @unlink($path);
        }

        $record = UksRecord::query()->first();
        $templateSheets = (new UksRecordImportTemplateExport)->sheets();
        $templateRows = $templateSheets[0]->array();
        $exportRows = (new UksRecordExport)->array();

        $statsWidget = new class extends UksStatsOverview
        {
            public function exposeStats(): array
            {
                return $this->getStats();
            }
        };

        $categoryChart = new class extends UksCategoryChart
        {
            public function exposeData(): array
            {
                return $this->getData();
            }
        };

        $measurementChart = new class extends UksMeasurementChart
        {
            public function exposeData(): array
            {
                return $this->getData();
            }
        };

        $stats = collect($statsWidget->exposeStats())
            ->mapWithKeys(fn (Stat $stat): array => [(string) $stat->getLabel() => (string) $stat->getValue()])
            ->all();

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame('Observasi UKS lanjutan', $record?->penanganan);
        $this->assertEquals(45.5, (float) $record?->berat_badan);
        $this->assertContains('berat_badan', $templateRows[0]);
        $this->assertContains('lingkar_kepala', $templateRows[0]);
        $this->assertSame('nama_siswa', $exportRows[0][1]);
        $this->assertSame('Abiel', $exportRows[1][1]);
        $this->assertSame('1', $stats['Total Kunjungan UKS']);
        $this->assertSame([1], $categoryChart->exposeData()['datasets'][0]['data']);
        $this->assertSame([45.5, 162.0, 53.0], $measurementChart->exposeData()['datasets'][0]['data']);
    }

    public function test_guru_tendik_widgets_export_and_import_support_history_and_controlled_fields(): void
    {
        $guru = GuruTendik::query()->create([
            'nama' => 'Ustadz Rahmat',
            'nip' => '19870101',
            'jenis_ptk' => 'Guru',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        GuruTendik::query()->create([
            'nama' => 'Bu Nisa',
            'jenis_ptk' => 'Tendik',
            'jk' => 'P',
            'status' => 'aktif',
        ]);

        $pamong = GuruTendik::query()->create([
            'nama' => 'Ustadz Pamong',
            'jenis_ptk' => 'Pamong',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $guru->tugasTambahan()->create([
            'tugas_tambahan' => 'Wali Kelas X-A',
            'no_sk' => 'SK-001/AFBS/2026',
            'tmt' => now()->subMonth()->toDateString(),
            'tst' => null,
            'keterangan' => 'Aktif berjalan',
        ]);

        $statsWidget = new class extends GuruTendikStatsOverview
        {
            public function exposeStats(): array
            {
                return $this->getStats();
            }
        };

        $jenisChart = new class extends GuruTendikJenisPtkChart
        {
            public function exposeData(): array
            {
                return $this->getData();
            }
        };

        $genderChart = new class extends GuruTendikGenderChart
        {
            public function exposeData(): array
            {
                return $this->getData();
            }
        };

        $stats = collect($statsWidget->exposeStats())
            ->mapWithKeys(fn (Stat $stat): array => [(string) $stat->getLabel() => (string) $stat->getValue()])
            ->all();

        $guruListPage = new class extends ListGuruTendiks
        {
            public function exposeHeaderWidgets(): array
            {
                return $this->getHeaderWidgets();
            }
        };

        $this->assertSame([
            GuruTendikAccountStatsOverview::class,
            GuruTendikStatsOverview::class,
        ], $guruListPage->exposeHeaderWidgets());

        $this->assertSame('3', $stats['Total Guru/Tendik']);
        $this->assertSame('1', $stats['Guru']);
        $this->assertSame('1', $stats['Tendik']);
        $this->assertSame('1', $stats['Pamong']);
        $this->assertSame('2', $stats['Laki-laki']);
        $this->assertSame('1', $stats['Perempuan']);
        $this->assertSame('1', $stats['Punya Tugas Aktif']);
        $this->assertStringContainsString('chart_jenis_ptk=Guru', $jenisChart->exposeData()['datasets'][0]['segmentDetails'][0]['url']);
        $this->assertStringContainsString('chart_jenis_ptk=Pamong', $jenisChart->exposeData()['datasets'][0]['segmentDetails'][2]['url']);
        $this->assertStringContainsString('chart_jk=L', $genderChart->exposeData()['datasets'][0]['segmentDetails'][0]['url']);
        $this->assertSame(['pamong_putra'], AdminRoleTemplateSupport::suggestedTemplatesForGuruTendik($pamong));

        $provisionedPamong = app(GuruTendikAccountProvisioner::class)->provisionOrResetForGuru($pamong)['user']->fresh();
        $this->assertTrue($provisionedPamong->hasRole('pamong_putra'));
        $this->assertTrue($provisionedPamong->isBoardingPamong());

        $manualAccessUser = User::query()->create([
            'name' => 'Akun Akses Pamong',
            'username' => 'akses-pamong',
            'password' => 'secret123',
        ]);
        $manualAccessUser->assignRole('guru');
        UserResource::applyDivisionTemplatesToUser($manualAccessUser, ['pamong_putri']);
        $manualAccessUser->refresh();
        $this->assertTrue($manualAccessUser->hasRole('pamong_putri'));
        $this->assertTrue($manualAccessUser->canManageModule('boarding_rapot'));

        $export = new GuruTendikExport;
        $sheets = $export->sheets();
        $this->assertSame('guru_tendik', $sheets[0]->title());
        $this->assertSame('tugas_tambahan', $sheets[1]->title());
        $this->assertCount(4, $sheets[0]->array());
        $this->assertCount(2, $sheets[1]->array());
        $this->assertContains('niy', $sheets[0]->array()[0]);

        $template = new GuruTendikImportTemplateExport;
        $this->assertSame('template_import_guru_tendik', $template->sheets()[0]->title());
        $this->assertSame('panduan', $template->sheets()[1]->title());
        $this->assertContains('niy', $template->sheets()[0]->array()[0]);

        $path = $this->createWorkbook([
            ['nama', 'niy', 'nuptk', 'nik', 'jenis_ptk', 'jk', 'tempat_lahir', 'tanggal_lahir', 'status'],
            ['Ustadz Fikri', '1987009', '', '', 'Guru', 'L', 'Bogor', '1987-02-01', 'aktif'],
            ['Ustadz Fikri', '1987009', '', '', 'Guru', 'L', 'Bandung', '1987-02-01', 'aktif'],
            ['Ustadz Boarding', '1987010', '', '', 'pamong', 'L', 'Garut', '1988-02-01', 'aktif'],
        ]);

        try {
            $result = app(GuruTendikWorkbookImporter::class)->import($path);
        } finally {
            @unlink($path);
        }

        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertDatabaseHas('guru_tendik', [
            'nama' => 'Ustadz Fikri',
            'tempat_lahir' => 'Bandung',
            'jenis_ptk' => 'Guru',
            'jk' => 'L',
        ]);
        $this->assertDatabaseHas('guru_tendik', [
            'nama' => 'Ustadz Boarding',
            'jenis_ptk' => 'Pamong',
            'jk' => 'L',
        ]);
    }

    protected function createWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            $columnIndex = 1;

            foreach ($row as $value) {
                $sheet->setCellValueByColumnAndRow($columnIndex, $rowIndex + 1, $value);
                $columnIndex++;
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'uks-import-');

        if ($path === false) {
            $this->fail('Gagal membuat workbook UKS untuk test.');
        }

        $finalPath = $path.'.xlsx';
        (new Xlsx($spreadsheet))->save($finalPath);

        if (is_file($path)) {
            @unlink($path);
        }

        return $finalPath;
    }
}
