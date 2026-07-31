<?php

namespace Tests\Feature;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Filament\Pages\Assessment\AsasHub;
use App\Filament\Pages\Assessment\AssessmentDashboard;
use App\Filament\Pages\Assessment\AssessmentMasterImport;
use App\Filament\Pages\Assessment\AstsHub;
use App\Filament\Pages\Assessment\AstsInputScores;
use App\Filament\Resources\AssessmentAuditLogResource\Pages\ListAssessmentAuditLogs;
use App\Filament\Resources\AssessmentPeriodResource;
use App\Filament\Resources\AssessmentSchemeResource;
use App\Filament\Resources\AssessmentSchemeResource\Pages\CreateAssessmentScheme;
use App\Filament\Resources\AssessmentSubjectResource\Pages\ListAssessmentSubjects;
use App\Filament\Resources\GuruTendikResource\Pages\EditGuruTendik;
use App\Filament\Resources\GuruTendikResource\RelationManagers\AssessmentHomeroomAssignmentsRelationManager;
use App\Filament\Resources\GuruTendikResource\RelationManagers\AssessmentTeachingAssignmentsRelationManager;
use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\Assessment\AssessmentScheme;
use App\Models\Assessment\AuditLog;
use App\Models\Assessment\HomeroomAssignment;
use App\Models\Assessment\Semester;
use App\Models\Assessment\Subject;
use App\Models\Assessment\TeachingAssignment;
use App\Models\GuruTendik;
use App\Models\Rombel;
use App\Models\User;
use App\Support\Admin\AdminModuleAccess;
use App\Support\Admin\AdminSchoolNavigation;
use App\Support\AssessmentMaster\AssessmentMasterWorkbookImporter;
use Database\Seeders\InitialAdminSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class AssessmentAdminIntegrationTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    /** @var list<string> */
    private array $workbookPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        $this->createLegacyMasterTables();

        $migration = require database_path(
            'migrations/2026_07_31_080000_create_assessment_foundation_tables.php',
        );
        $migration->up();
        $reportStructureMigration = require database_path(
            'migrations/2026_07_31_120000_extend_assessment_report_structure.php',
        );
        $reportStructureMigration->up();
        $pipelineMigration = require database_path(
            'migrations/2026_07_31_190000_add_assessment_report_generation_runs.php',
        );
        $pipelineMigration->up();

        Artisan::call('assessment:install-defaults');
        config(['assessment.enabled' => true]);
    }

    protected function tearDown(): void
    {
        foreach ($this->workbookPaths as $path) {
            File::delete($path);
        }

        parent::tearDown();
    }

    public function test_workbook_preview_and_apply_are_upserts_and_never_delete_omitted_rows(): void
    {
        [$teacher, $rombel] = $this->createLegacyReferences();
        $customSubject = Subject::query()->create([
            'code' => 'CUSTOM',
            'name' => 'Mapel Lokal yang Tidak Ada di Workbook',
            'is_active' => true,
        ]);

        $path = $this->makeWorkbook(
            yearCode: '2026-2027',
            semesterCode: '2026-2027-GANJIL',
            subjectCode: 'MAT',
            subjectName: 'Matematika',
            teacherId: (int) $teacher->getKey(),
            rombelId: (int) $rombel->getKey(),
        );

        $importer = app(AssessmentMasterWorkbookImporter::class);
        $preview = $importer->preview($path);

        $this->assertSame([], $preview['errors']);
        $this->assertSame(5, $preview['summary']['create']);
        $this->assertSame(0, $preview['summary']['update']);
        $this->assertSame('create', $preview['payload']['subjects'][0]['action']);

        $result = $importer->apply($preview, null);

        $this->assertSame(['created' => 5, 'updated' => 0, 'unchanged' => 0], $result);
        $this->assertDatabaseHas('assessment_academic_years', ['code' => '2026-2027']);
        $this->assertDatabaseHas('assessment_semesters', ['code' => '2026-2027-GANJIL']);
        $this->assertDatabaseHas('assessment_subjects', ['code' => 'MAT', 'name' => 'Matematika']);
        $this->assertDatabaseHas('assessment_teaching_assignments', [
            'teacher_id' => $teacher->getKey(),
            'rombel_id' => $rombel->getKey(),
        ]);
        $this->assertDatabaseHas('assessment_homeroom_assignments', [
            'teacher_id' => $teacher->getKey(),
            'rombel_id' => $rombel->getKey(),
        ]);

        $updatedPath = $this->makeWorkbook(
            yearCode: '2026-2027',
            semesterCode: '2026-2027-GANJIL',
            subjectCode: 'MAT',
            subjectName: 'Matematika Lanjutan',
            teacherId: (int) $teacher->getKey(),
            rombelId: (int) $rombel->getKey(),
        );
        $updatedPreview = $importer->preview($updatedPath);

        $this->assertSame([], $updatedPreview['errors']);
        $this->assertGreaterThanOrEqual(1, $updatedPreview['summary']['update']);
        $updatedResult = $importer->apply($updatedPreview, null);

        $this->assertGreaterThanOrEqual(1, $updatedResult['updated']);
        $this->assertSame('Matematika Lanjutan', Subject::query()->where('code', 'MAT')->value('name'));
        $this->assertTrue($customSubject->fresh()->exists);
        $this->assertSame(
            'Mapel Lokal yang Tidak Ada di Workbook',
            $customSubject->fresh()->name,
            'Baris yang tidak ada di workbook tidak boleh dihapus atau dinonaktifkan.',
        );
        $this->assertSame(2, Subject::query()->count());
        $this->assertSame(1, TeachingAssignment::query()->count());
        $this->assertSame(1, HomeroomAssignment::query()->count());
    }

    public function test_new_workbook_imports_report_group_and_order_while_legacy_workbook_stays_compatible(): void
    {
        [$teacher, $rombel] = $this->createLegacyReferences();
        $legacyPath = $this->makeWorkbook(
            yearCode: '2026-2027',
            semesterCode: '2026-2027-GANJIL',
            subjectCode: 'LEGACY',
            subjectName: 'Mapel Format Lama',
            teacherId: (int) $teacher->getKey(),
            rombelId: (int) $rombel->getKey(),
        );
        $legacyPreview = app(AssessmentMasterWorkbookImporter::class)->preview($legacyPath);

        $this->assertSame([], $legacyPreview['errors']);
        $this->assertSame('BELUM', $legacyPreview['payload']['subjects'][0]['report_group_code']);
        $this->assertStringContainsString('format lama', mb_strtolower(implode(' ', $legacyPreview['warnings'])));

        $newPath = $this->makeWorkbook(
            yearCode: '2026-2027',
            semesterCode: '2026-2027-GANJIL',
            subjectCode: 'MAT',
            subjectName: 'Matematika',
            teacherId: (int) $teacher->getKey(),
            rombelId: (int) $rombel->getKey(),
        );
        $spreadsheet = IOFactory::load($newPath);
        $spreadsheet->getSheetByName('MAPEL')->fromArray([
            ['KODE_MAPEL', 'NAMA_MAPEL', 'DESKRIPSI', 'KELOMPOK_KODE', 'KELOMPOK_NAMA', 'URUTAN_KELOMPOK', 'URUTAN_MAPEL', 'AKTIF'],
            ['MAT', 'Matematika', 'Numerasi', 'A', 'Kelompok A', 10, 20, 'YA'],
        ], null, 'A1');
        (new Xlsx($spreadsheet))->save($newPath);
        $spreadsheet->disconnectWorksheets();

        $preview = app(AssessmentMasterWorkbookImporter::class)->preview($newPath);

        $this->assertSame([], $preview['errors']);
        $this->assertSame('A', $preview['payload']['subjects'][0]['report_group_code']);
        $this->assertSame('Kelompok A', $preview['payload']['subjects'][0]['report_group_name']);
        $this->assertSame(10, $preview['payload']['subjects'][0]['report_group_sort_order']);
        $this->assertSame(20, $preview['payload']['subjects'][0]['sort_order']);

        app(AssessmentMasterWorkbookImporter::class)->apply($preview, null);
        $this->assertDatabaseHas('assessment_subjects', [
            'code' => 'MAT',
            'report_group_code' => 'A',
            'report_group_name' => 'Kelompok A',
            'report_group_sort_order' => 10,
            'sort_order' => 20,
        ]);
    }

    public function test_subject_report_metadata_can_be_explicitly_synced_only_to_unlocked_periods(): void
    {
        $admin = $this->createUser('assessment-subject-sync-admin', 'admin');
        $year = AcademicYear::query()->create([
            'code' => 'SYNC-2026',
            'name' => 'Tahun Sinkronisasi',
            'is_active' => true,
        ]);
        $semester = Semester::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'code' => 'SYNC-GANJIL',
            'name' => 'Semester Sinkronisasi',
            'is_active' => true,
        ]);
        $subject = Subject::query()->create([
            'code' => 'SYNC-MAT',
            'name' => 'Matematika Sinkron',
            'report_group_code' => 'A',
            'report_group_name' => 'Kelompok A',
            'report_group_sort_order' => 10,
            'sort_order' => 20,
            'is_active' => true,
        ]);
        $period = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'assessment_semester_id' => $semester->getKey(),
            'code' => 'ASTS-SYNC',
            'name' => 'ASTS Sinkron',
            'type' => AssessmentType::ASTS,
            'status' => AssessmentPeriodStatus::OPEN,
            'created_by' => $admin->getKey(),
        ]);
        $rombel = AssessmentPeriodRombel::factory()->create([
            'assessment_period_id' => $period->getKey(),
        ]);
        $assignment = AssessmentPeriodAssignment::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'assessment_subject_id' => $subject->getKey(),
            'subject_group_code_snapshot' => 'BELUM',
            'subject_group_name_snapshot' => 'Belum Dikelompokkan',
            'subject_group_sort_order_snapshot' => 999,
            'subject_sort_order_snapshot' => 0,
        ]);

        Livewire::actingAs($admin)
            ->test(ListAssessmentSubjects::class)
            ->callTableBulkAction('syncUnlockedPeriodMetadata', [$subject])
            ->assertHasNoTableBulkActionErrors();

        $assignment->refresh();
        $this->assertSame('A', $assignment->subject_group_code_snapshot);
        $this->assertSame('Kelompok A', $assignment->subject_group_name_snapshot);
        $this->assertSame(10, $assignment->subject_group_sort_order_snapshot);
        $this->assertSame(20, $assignment->subject_sort_order_snapshot);
        $this->assertDatabaseHas('assessment_audit_logs', [
            'assessment_period_id' => $period->getKey(),
            'event' => 'assignment.report_metadata_synchronized',
            'subject_id' => $assignment->getKey(),
        ]);
    }

    public function test_preview_fingerprint_prevents_modified_payload_from_being_applied(): void
    {
        [$teacher, $rombel] = $this->createLegacyReferences();
        $path = $this->makeWorkbook(
            yearCode: '2026-2027',
            semesterCode: '2026-2027-GANJIL',
            subjectCode: 'BIN',
            subjectName: 'Bahasa Indonesia',
            teacherId: (int) $teacher->getKey(),
            rombelId: (int) $rombel->getKey(),
        );

        $importer = app(AssessmentMasterWorkbookImporter::class);
        $preview = $importer->preview($path);
        $preview['payload']['subjects'][0]['name'] = 'Nama yang Dimanipulasi';

        try {
            $importer->apply($preview, null);
            $this->fail('Payload pratinjau yang berubah seharusnya ditolak.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('pratinjau tidak valid', $exception->getMessage());
        }

        $this->assertSame(0, AcademicYear::query()->count());
        $this->assertSame(0, Semester::query()->count());
        $this->assertSame(0, Subject::query()->count());
        $this->assertSame(0, TeachingAssignment::query()->count());
        $this->assertSame(0, HomeroomAssignment::query()->count());
    }

    public function test_preview_fingerprint_also_protects_validation_errors_and_warnings(): void
    {
        [$teacher, $rombel] = $this->createLegacyReferences();
        $path = $this->makeWorkbook(
            yearCode: '2026-2027',
            semesterCode: '2026-2027-GANJIL',
            subjectCode: 'IPA',
            subjectName: 'Ilmu Pengetahuan Alam',
            teacherId: (int) $teacher->getKey(),
            rombelId: (int) $rombel->getKey(),
        );

        $importer = app(AssessmentMasterWorkbookImporter::class);
        $preview = $importer->preview($path);
        $this->assertSame([], $preview['errors']);

        $preview['errors'] = ['Kesalahan validasi yang dicoba dimanipulasi.'];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('pratinjau tidak valid');

        $importer->apply($preview, null);
    }

    public function test_workbook_preview_rejects_renamed_or_reordered_required_headers(): void
    {
        [$teacher, $rombel] = $this->createLegacyReferences();
        $path = $this->makeWorkbook(
            yearCode: '2026-2027',
            semesterCode: '2026-2027-GANJIL',
            subjectCode: 'IPA',
            subjectName: 'Ilmu Pengetahuan Alam',
            teacherId: (int) $teacher->getKey(),
            rombelId: (int) $rombel->getKey(),
        );

        $spreadsheet = IOFactory::load($path);
        $spreadsheet->getSheetByName('MAPEL')->setCellValue('A1', 'KODE_MAPEL_DIUBAH');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        $preview = app(AssessmentMasterWorkbookImporter::class)->preview($path);

        $this->assertNotEmpty($preview['errors']);
        $this->assertStringContainsString('Header sheet MAPEL harus persis', implode(' ', $preview['errors']));
        $this->assertSame([], $preview['payload']);
    }

    public function test_feature_flag_module_menu_access_and_template_route_authorization(): void
    {
        $this->createLegacyReferences();

        $viewer = $this->createUser('assessment-viewer', 'tu');
        $viewer->forceFill([
            'module_access_levels' => ['penilaian' => AdminModuleAccess::VIEW],
        ])->save();

        $admin = $this->createUser('assessment-admin', 'admin');

        $this->assertContains(AdminSchoolNavigation::GROUP, User::navigationGroupOptions());
        $this->assertNotContains('Penilaian', User::navigationGroupOptions());
        $this->assertSame(
            AdminSchoolNavigation::GROUP,
            AdminModuleAccess::definition('penilaian')['group'],
        );
        $this->assertSame(
            AdminSchoolNavigation::GROUP,
            AdminSchoolNavigation::effectiveGroupForClass(AstsHub::class),
        );
        $this->assertSame(
            AdminSchoolNavigation::GROUP,
            AdminSchoolNavigation::effectiveGroupForClass(AssessmentDashboard::class),
        );
        $assessmentParents = collect(AdminSchoolNavigation::parentNavigationItems([
            AssessmentDashboard::class,
            AstsHub::class,
            AsasHub::class,
        ]))->keyBy(fn ($item): string => $item->getLabel());
        $this->assertSame(
            ['Penilaian'],
            $assessmentParents->keys()->all(),
        );
        $this->assertTrue(
            $assessmentParents->every(
                fn ($item): bool => $item->getGroup() === AdminSchoolNavigation::GROUP,
            ),
        );
        $this->assertContains(
            AssessmentDashboard::class,
            AdminModuleAccess::itemClassesForLevels(['penilaian' => AdminModuleAccess::VIEW]),
        );
        $this->assertContains(
            AstsHub::class,
            AdminModuleAccess::itemClassesForLevels(['penilaian' => AdminModuleAccess::VIEW]),
        );
        $this->assertContains(
            AsasHub::class,
            AdminModuleAccess::itemClassesForLevels(['penilaian' => AdminModuleAccess::VIEW]),
        );
        $this->assertContains(
            AstsInputScores::class,
            AdminModuleAccess::itemClassesForLevels(['penilaian' => AdminModuleAccess::VIEW]),
        );
        $this->assertContains(
            AssessmentPeriodResource::class,
            AdminModuleAccess::itemClassesForLevels(['penilaian' => AdminModuleAccess::VIEW]),
        );

        $this->actingAs($viewer);
        $this->assertTrue(AssessmentDashboard::canAccess());
        $this->assertTrue(AssessmentDashboard::shouldRegisterNavigation());
        $this->assertTrue(AstsHub::shouldRegisterNavigation());
        $this->assertTrue(AsasHub::shouldRegisterNavigation());
        $this->assertFalse(AstsInputScores::canAccess());
        $this->assertFalse(AstsInputScores::shouldRegisterNavigation());
        $this->assertFalse(
            AssessmentPeriodResource::canViewAny(),
            'Akses module-level view tidak boleh membuka data resource pengaturan periode.',
        );
        $this->assertFalse(AssessmentPeriodResource::shouldRegisterNavigation());
        $this->assertFalse(AssessmentPeriodResource::canCreate());

        $teacherWithPermissionButHiddenModule = $this->createUser(
            'assessment-hidden-teacher',
            'guru_mapel',
        );
        $teacherWithPermissionButHiddenModule->forceFill([
            'module_access_levels' => ['penilaian' => AdminModuleAccess::NONE],
        ])->save();
        $this->actingAs($teacherWithPermissionButHiddenModule);
        $this->assertFalse(AssessmentDashboard::canAccess());
        $this->assertFalse(AstsInputScores::canAccess());

        $viewer->forceFill([
            'module_access_levels' => ['penilaian' => AdminModuleAccess::MANAGE],
        ])->save();
        $this->actingAs($viewer);
        $this->assertTrue(AssessmentDashboard::canAccess());
        $this->assertFalse(
            AssessmentPeriodResource::canCreate(),
            'Akses modul manage hanya mengatur visibilitas dan tidak boleh menggantikan permission period.manage.',
        );
        $this->assertTrue(
            Gate::forUser($viewer)->denies('create', AssessmentPeriod::class),
            'Policy direct juga tidak boleh menganggap permission module manage sebagai period.manage.',
        );

        config(['assessment.enabled' => false]);
        $this->assertFalse(AssessmentDashboard::canAccess());
        $this->actingAs($admin)
            ->get(route('admin.assessment.master-template'))
            ->assertNotFound();

        config(['assessment.enabled' => true]);
        auth()->logout();
        $this->get(route('admin.assessment.master-template'))
            ->assertRedirect(route('filament.admin.auth.login'));
        $this->getJson(route('admin.assessment.master-template'))
            ->assertUnauthorized();

        $this->actingAs($viewer)
            ->getJson(route('admin.assessment.master-template'))
            ->assertForbidden();

        $response = $this->actingAs($admin)
            ->get(route('admin.assessment.master-template'));

        $response->assertOk();
        $this->assertStringContainsString(
            'template-master-penilaian-asts-asas.xlsx',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('cache-control'),
        );

        Livewire::actingAs($admin)
            ->test(AssessmentDashboard::class)
            ->assertSeeHtml('assessment-dashboard-hero')
            ->assertSeeHtml('assessment-settings-card')
            ->assertSee('Alur Menyiapkan ASTS dan ASAS')
            ->assertSee('Guru dan Akun Login')
            ->assertSee('Rombel dan Siswa Aktif')
            ->assertSee('Mapel dan Penugasan Resmi')
            ->assertSee('Guru Mapel & Kelas')
            ->assertSee('Wali Kelas')
            ->assertSee('Atur di Guru & Tendik')
            ->assertSee('Preflight dan Buka Periode')
            ->assertSee('Penilaian ASTS–ASAS')
            ->assertSee('Menu Pengaturan')
            ->assertSee('Periode Penilaian')
            ->assertSee('Komponen dan Bobot')
            ->assertSee('Template Rapor')
            ->assertSeeHtml('assessment-readiness-grid')
            ->assertSeeHtml('assessment-audit-shell');

        Livewire::actingAs($admin)
            ->test(AstsHub::class)
            ->assertSeeHtml('assessment-type-hero')
            ->assertSeeHtml('assessment-type-action-card')
            ->assertSee('Semua kebutuhan ASTS dalam satu halaman')
            ->assertSee('Input Nilai Saya')
            ->assertSee('Status Pengumpulan')
            ->assertSee('Rekap Wali Kelas')
            ->assertSee('Cetak Rapor ASTS');

        Livewire::actingAs($admin)
            ->test(AsasHub::class)
            ->assertSeeHtml('assessment-type-hero')
            ->assertSeeHtml('assessment-type-action-card')
            ->assertSee('Semua kebutuhan ASAS dalam satu halaman')
            ->assertSee('Input Nilai Saya')
            ->assertSee('Status Pengumpulan')
            ->assertSee('Rekap Wali Kelas')
            ->assertSee('Cetak Rapor Semester');

        $year = AcademicYear::query()->create([
            'code' => '2026-2027',
            'name' => 'Tahun Pelajaran 2026/2027',
            'is_active' => true,
        ]);
        $semester = Semester::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'code' => '2026-2027-GANJIL',
            'name' => 'Semester Ganjil',
            'is_active' => true,
        ]);
        $period = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'assessment_semester_id' => $semester->getKey(),
            'code' => 'ASTS-ADMIN-INTEGRATION',
            'name' => 'ASTS Admin Integration',
            'type' => AssessmentType::ASTS,
            'status' => AssessmentPeriodStatus::OPEN,
        ]);

        $this->actingAs($admin);
        $this->assertFalse(
            AssessmentPeriodResource::canEdit($period),
            'Direct URL edit tidak boleh mengubah periode yang sudah dibuka.',
        );
        $this->assertFalse(AssessmentPeriodResource::canDelete($period));

        $period->forceFill(['status' => AssessmentPeriodStatus::LOCKED])->save();
        $this->assertFalse(AssessmentPeriodResource::canEdit($period));
        $this->assertFalse(AssessmentPeriodResource::canDelete($period));

        $period->forceFill(['status' => AssessmentPeriodStatus::PUBLISHED])->save();
        $this->assertFalse(AssessmentPeriodResource::canEdit($period));
        $this->assertFalse(AssessmentPeriodResource::canDelete($period));

        $period->forceFill(['status' => AssessmentPeriodStatus::DRAFT])->save();
        $this->assertTrue(AssessmentPeriodResource::canEdit($period));
        $this->assertTrue(AssessmentPeriodResource::canDelete($period));

        $this->assertTrue($admin->hasPermissionTo('penilaian.report.generate'));
        (new InitialAdminSeeder)->run();
        $this->assertTrue(
            $admin->fresh()->hasPermissionTo('penilaian.report.generate'),
            'Menjalankan ulang seeder awal tidak boleh menghapus permission Penilaian.',
        );
    }

    public function test_master_import_preview_renders_summary_and_rows_without_blade_errors(): void
    {
        $admin = $this->createUser('assessment-import-preview-admin', 'admin');

        Livewire::actingAs($admin)
            ->test(AssessmentMasterImport::class)
            ->assertSeeHtml('assessment-import-hero')
            ->assertSee('Siapkan hubungan guru, mapel, kelas, dan semester')
            ->assertSee('Empat tahap sebelum data diterapkan')
            ->assertSee('Data yang dihasilkan')
            ->set('preview', [
                'summary' => [
                    'create' => 1,
                    'update' => 0,
                    'unchanged' => 0,
                    'warnings' => 0,
                    'errors' => 0,
                ],
                'errors' => [],
                'warnings' => [],
                'payload' => [
                    'academic_years' => [[
                        'action' => 'create',
                        'code' => '2026-2027',
                        'name' => 'Tahun Pelajaran 2026/2027',
                        'starts_on' => '2026-07-01',
                        'ends_on' => '2027-06-30',
                        'is_active' => true,
                    ]],
                    'semesters' => [],
                    'subjects' => [],
                    'teaching_assignments' => [],
                    'homeroom_assignments' => [],
                ],
            ])
            ->assertSee('Rincian pratinjau')
            ->assertSee('Tahun Pelajaran 2026/2027')
            ->assertSee('1 baris');
    }

    public function test_teacher_subject_and_homeroom_assignments_are_managed_from_guru_tendik(): void
    {
        $admin = $this->createUser('assessment-guru-assignment-admin', 'admin');
        $year = AcademicYear::query()->create([
            'code' => '2030-2031',
            'name' => 'Tahun Pelajaran 2030/2031',
            'is_active' => true,
        ]);
        $semester = Semester::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'code' => '2030-2031-GANJIL',
            'name' => 'Semester Ganjil',
            'is_active' => true,
        ]);
        $subject = Subject::query()->create([
            'code' => 'MAT-2030',
            'name' => 'Matematika',
            'is_active' => true,
        ]);
        $rombel = Rombel::query()->create([
            'nama' => 'XI 1',
            'angkatan' => '2030',
            'is_active' => true,
        ]);
        $guru = GuruTendik::query()->create([
            'nama' => 'Guru Penilaian Terintegrasi',
            'status' => 'aktif',
        ]);

        Livewire::actingAs($admin)
            ->test(AssessmentTeachingAssignmentsRelationManager::class, [
                'ownerRecord' => $guru,
                'pageClass' => EditGuruTendik::class,
            ])
            ->call('loadTable')
            ->assertSee('Mapel dan Kelas Mengajar')
            ->callTableAction('create', data: [
                'assessment_semester_id' => $semester->getKey(),
                'assessment_subject_id' => $subject->getKey(),
                'rombel_id' => $rombel->getKey(),
                'is_active' => true,
            ])
            ->assertHasNoFormErrors();

        Livewire::actingAs($admin)
            ->test(AssessmentHomeroomAssignmentsRelationManager::class, [
                'ownerRecord' => $guru,
                'pageClass' => EditGuruTendik::class,
            ])
            ->call('loadTable')
            ->assertSee('Wali Kelas')
            ->callTableAction('create', data: [
                'assessment_semester_id' => $semester->getKey(),
                'rombel_id' => $rombel->getKey(),
                'is_active' => true,
            ])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('assessment_teaching_assignments', [
            'assessment_semester_id' => $semester->getKey(),
            'assessment_subject_id' => $subject->getKey(),
            'teacher_id' => $guru->getKey(),
            'rombel_id' => $rombel->getKey(),
            'teacher_name_snapshot' => $guru->nama,
            'subject_name_snapshot' => $subject->name,
            'rombel_name_snapshot' => $rombel->nama,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('assessment_homeroom_assignments', [
            'assessment_semester_id' => $semester->getKey(),
            'teacher_id' => $guru->getKey(),
            'rombel_id' => $rombel->getKey(),
            'teacher_name_snapshot' => $guru->nama,
            'rombel_name_snapshot' => $rombel->nama,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('assessment_audit_logs', [
            'event' => 'teaching_assignment.created',
            'subject_type' => TeachingAssignment::class,
        ]);
        $this->assertDatabaseHas('assessment_audit_logs', [
            'event' => 'homeroom_assignment.created',
            'subject_type' => HomeroomAssignment::class,
        ]);

        $this->view('filament.resources.guru-tendik-resource.partials.assessment-integration', [
            'guru' => $guru,
            'teachingCount' => 1,
            'homeroomCount' => 1,
        ])
            ->assertSeeHtml('guru-assessment-integration')
            ->assertSee('Mapel, kelas mengajar, dan wali kelas')
            ->assertSee('Penilaian ASTS–ASAS');
    }

    public function test_scheme_guide_and_weight_preview_explain_the_result_before_save(): void
    {
        $warning = AssessmentSchemeResource::weightPreview([
            ['weight' => 40, 'settings' => ['is_active' => true]],
            ['weight' => 50, 'settings' => ['is_active' => true]],
            ['weight' => 10, 'settings' => ['is_active' => false]],
        ])->toHtml();

        $this->assertStringContainsString('assessment-weight-preview is-warning', $warning);
        $this->assertStringContainsString('90,00%', $warning);
        $this->assertStringContainsString('Belum siap', $warning);

        $ready = AssessmentSchemeResource::weightPreview([
            ['weight' => 40, 'settings' => ['is_active' => true]],
            ['weight' => 60, 'settings' => ['is_active' => true]],
        ])->toHtml();

        $this->assertStringContainsString('assessment-weight-preview is-ready', $ready);
        $this->assertStringContainsString('100,00%', $ready);
        $this->assertStringContainsString('Siap disimpan', $ready);
    }

    public function test_scheme_with_one_relationship_component_is_created_successfully(): void
    {
        $admin = $this->createUser('assessment-valid-scheme-admin', 'admin');
        $year = AcademicYear::query()->create([
            'code' => '2029-2030',
            'name' => 'Tahun Pelajaran 2029/2030',
            'is_active' => true,
        ]);
        $semester = Semester::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'code' => '2029-2030-GANJIL',
            'name' => 'Semester Ganjil',
            'is_active' => true,
        ]);
        $period = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'assessment_semester_id' => $semester->getKey(),
            'code' => 'ASTS-SKEMA-VALID',
            'name' => 'ASTS Skema Valid',
            'type' => AssessmentType::ASTS,
            'status' => AssessmentPeriodStatus::DRAFT,
            'settings' => ['rombel_ids' => []],
            'created_by' => $admin->getKey(),
        ]);

        Livewire::actingAs($admin)
            ->test(CreateAssessmentScheme::class)
            ->fillForm([
                'assessment_period_id' => $period->getKey(),
                'name' => 'Skema Satu Komponen',
                'rounding_precision' => 2,
                'minimum_score' => 0,
                'maximum_score' => 100,
                'is_active' => true,
                'settings' => [
                    'kkm' => 75,
                    'fallback_predicate' => 'D',
                    'predicates' => [],
                ],
                'components' => [[
                    'code' => 'UTAMA',
                    'name' => 'Nilai Utama',
                    'domain' => 'Kompetensi Utama',
                    'weight' => 100,
                    'maximum_score' => 100,
                    'score_source' => 'manual',
                    'is_required' => true,
                    'settings' => ['is_active' => true],
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $scheme = AssessmentScheme::query()
            ->where('name', 'Skema Satu Komponen')
            ->firstOrFail();

        $this->assertDatabaseHas('assessment_components', [
            'assessment_scheme_id' => $scheme->getKey(),
            'code' => 'UTAMA',
            'name' => 'Nilai Utama',
            'weight' => 100,
        ]);
    }

    public function test_invalid_scheme_component_relationship_is_rolled_back_atomically(): void
    {
        $admin = $this->createUser('assessment-scheme-admin', 'admin');
        $year = AcademicYear::query()->create([
            'code' => '2027-2028',
            'name' => 'Tahun Pelajaran 2027/2028',
            'is_active' => true,
        ]);
        $semester = Semester::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'code' => '2027-2028-GANJIL',
            'name' => 'Semester Ganjil',
            'is_active' => true,
        ]);
        $period = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'assessment_semester_id' => $semester->getKey(),
            'code' => 'ASTS-ROLLBACK',
            'name' => 'ASTS Uji Rollback',
            'type' => AssessmentType::ASTS,
            'status' => AssessmentPeriodStatus::DRAFT,
            'settings' => ['rombel_ids' => []],
            'created_by' => $admin->getKey(),
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CreateAssessmentScheme::class)
            ->assertSeeHtml('assessment-scheme-guide')
            ->assertSee('Apa itu Komponen dan Bobot?')
            ->assertSee('Maksud')
            ->assertSee('Tujuan')
            ->assertSee('Hasil')
            ->assertSee('Total 100%')
            ->fillForm([
                'assessment_period_id' => $period->getKey(),
                'name' => 'Skema Bobot Tidak Valid',
                'rounding_precision' => 0,
                'minimum_score' => 0,
                'maximum_score' => 100,
                'is_active' => true,
                'settings' => [
                    'kkm' => 75,
                    'fallback_predicate' => 'D',
                    'predicates' => [[
                        'label' => 'A',
                        'minimum_score' => 90,
                    ]],
                ],
                'components' => [[
                    'code' => 'UTAMA',
                    'name' => 'Komponen Utama',
                    'weight' => 90,
                    'maximum_score' => 100,
                    'score_source' => 'manual',
                    'is_required' => true,
                    'settings' => ['is_active' => true],
                ]],
            ])
            ->call('create');

        $component->assertHasFormErrors(['components']);

        $this->assertDatabaseMissing('assessment_schemes', [
            'name' => 'Skema Bobot Tidak Valid',
        ]);
        $this->assertDatabaseCount('assessment_components', 0);

        $period->forceFill(['status' => AssessmentPeriodStatus::OPEN])->save();
        Livewire::actingAs($admin)
            ->test(CreateAssessmentScheme::class)
            ->fillForm([
                'assessment_period_id' => $period->getKey(),
                'name' => 'Skema Setelah Periode Dibuka',
                'rounding_precision' => 0,
                'minimum_score' => 0,
                'maximum_score' => 100,
                'is_active' => true,
                'settings' => [
                    'kkm' => 75,
                    'fallback_predicate' => 'D',
                    'predicates' => [[
                        'label' => 'A',
                        'minimum_score' => 90,
                    ]],
                ],
                'components' => [[
                    'code' => 'UTAMA',
                    'name' => 'Komponen Utama',
                    'weight' => 100,
                    'maximum_score' => 100,
                    'score_source' => 'manual',
                    'is_required' => true,
                    'settings' => ['is_active' => true],
                ]],
            ])
            ->call('create')
            ->assertHasFormErrors(['assessment_period_id']);

        $this->assertDatabaseMissing('assessment_schemes', [
            'name' => 'Skema Setelah Periode Dibuka',
        ]);
    }

    public function test_dashboard_audit_never_falls_back_to_unscoped_logs(): void
    {
        $user = $this->createUser('assessment-audit-no-scope', 'guru_mapel');
        $user->givePermissionTo('penilaian.audit.view');
        $year = AcademicYear::query()->create([
            'code' => '2028-2029',
            'name' => 'Tahun Pelajaran 2028/2029',
            'is_active' => true,
        ]);
        $semester = Semester::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'code' => '2028-2029-GANJIL',
            'name' => 'Semester Ganjil',
            'is_active' => true,
        ]);
        $period = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'assessment_semester_id' => $semester->getKey(),
            'code' => 'ASTS-TERBATAS',
            'name' => 'ASTS Bukan Scope Guru',
            'type' => AssessmentType::ASTS,
            'status' => AssessmentPeriodStatus::DRAFT,
            'settings' => ['rombel_ids' => []],
            'created_by' => $user->getKey(),
        ]);
        AuditLog::query()->create([
            'assessment_period_id' => $period->getKey(),
            'actor_id' => $user->getKey(),
            'event' => 'period.secret_event',
            'subject_type' => AssessmentPeriod::class,
            'subject_id' => $period->getKey(),
            'created_at' => now(),
        ]);

        $component = Livewire::actingAs($user)->test(AssessmentDashboard::class);
        $component->set('periodId', null);
        $this->assertSame([], $component->instance()->getRecentAuditRows());
        $component->set('periodId', $period->getKey());
        $this->assertSame([], $component->instance()->getRecentAuditRows());
    }

    public function test_audit_detail_action_is_visible_only_to_an_authorized_audit_viewer(): void
    {
        $viewer = $this->createUser('assessment-audit-viewer', 'kurikulum');
        $viewer->givePermissionTo('penilaian.audit.view');
        $log = AuditLog::query()->create([
            'actor_id' => $viewer->getKey(),
            'event' => 'assessment.test_event',
            'subject_type' => User::class,
            'subject_id' => $viewer->getKey(),
            'old_values' => ['status' => 'draft'],
            'new_values' => ['status' => 'submitted'],
            'created_at' => now(),
        ]);

        $this->assertTrue(Gate::forUser($viewer)->allows('view', $log));
        $blockedUser = $this->createUser('assessment-audit-blocked', 'guru_mapel');
        $this->assertFalse(Gate::forUser($blockedUser)->allows('view', $log));

        Livewire::actingAs($viewer)
            ->test(ListAssessmentAuditLogs::class)
            ->call('loadTable')
            ->assertTableActionVisible('details', $log);
    }

    private function createLegacyMasterTables(): void
    {
        Schema::create('guru_tendik', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nama');
            $table->string('status')->default('aktif');
            $table->string('niy')->nullable();
            $table->string('nip')->nullable();
            $table->timestamps();
        });

        Schema::create('rombels', function (Blueprint $table): void {
            $table->id();
            $table->string('nama')->unique();
            $table->string('angkatan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * @return array{GuruTendik, Rombel}
     */
    private function createLegacyReferences(): array
    {
        $teacher = GuruTendik::query()->create([
            'nama' => 'Guru Penguji',
            'status' => 'aktif',
            'niy' => 'G-001',
        ]);
        $rombel = Rombel::query()->create([
            'nama' => 'XI 1',
            'angkatan' => 'XI',
            'is_active' => true,
        ]);

        $this->createUser('teacher-reference', 'guru_mapel', (int) $teacher->getKey());

        return [$teacher, $rombel];
    }

    private function createUser(string $username, string $role, ?int $teacherId = null): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::query()->create([
            'name' => str($username)->headline()->toString(),
            'username' => $username,
            'email' => null,
            'password' => 'test-password',
            'guru_tendik_id' => $teacherId,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function makeWorkbook(
        string $yearCode,
        string $semesterCode,
        string $subjectCode,
        string $subjectName,
        int $teacherId,
        int $rombelId,
    ): string {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()
            ->setTitle('TAHUN_SEMESTER')
            ->fromArray([
                ['TAHUN_KODE', 'TAHUN_NAMA', 'TAHUN_MULAI', 'TAHUN_SELESAI', 'SEMESTER_KODE', 'SEMESTER_NAMA', 'SEMESTER_MULAI', 'SEMESTER_SELESAI', 'AKTIF'],
                [$yearCode, "Tahun Pelajaran {$yearCode}", '2026-07-01', '2027-06-30', $semesterCode, 'Semester Ganjil', '2026-07-01', '2026-12-31', 'YA'],
            ]);
        $spreadsheet->createSheet()
            ->setTitle('MAPEL')
            ->fromArray([
                ['KODE_MAPEL', 'NAMA_MAPEL', 'DESKRIPSI', 'URUTAN', 'AKTIF'],
                [$subjectCode, $subjectName, '', 10, 'YA'],
            ]);
        $spreadsheet->createSheet()
            ->setTitle('PENUGASAN_GURU')
            ->fromArray([
                ['SEMESTER_KODE', 'MAPEL_KODE', 'NAMA_GURU', 'ID_GURU_SISTEM', 'NAMA_ROMBEL', 'ID_ROMBEL_SISTEM', 'AKTIF'],
                [$semesterCode, $subjectCode, 'Guru Penguji', $teacherId, 'XI 1', $rombelId, 'YA'],
            ]);
        $spreadsheet->createSheet()
            ->setTitle('WALI_KELAS')
            ->fromArray([
                ['SEMESTER_KODE', 'NAMA_GURU', 'ID_GURU_SISTEM', 'NAMA_ROMBEL', 'ID_ROMBEL_SISTEM', 'AKTIF'],
                [$semesterCode, 'Guru Penguji', $teacherId, 'XI 1', $rombelId, 'YA'],
            ]);

        $directory = storage_path('framework/testing/assessment-admin');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/'.str()->uuid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        $this->workbookPaths[] = $path;

        return $path;
    }
}
