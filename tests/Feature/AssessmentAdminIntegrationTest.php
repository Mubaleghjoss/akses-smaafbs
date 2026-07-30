<?php

namespace Tests\Feature;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Filament\Pages\Assessment\AssessmentDashboard;
use App\Filament\Pages\Assessment\AstsInputScores;
use App\Filament\Resources\AssessmentPeriodResource;
use App\Filament\Resources\AssessmentAuditLogResource\Pages\ListAssessmentAuditLogs;
use App\Filament\Resources\AssessmentSchemeResource\Pages\CreateAssessmentScheme;
use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AuditLog;
use App\Models\Assessment\HomeroomAssignment;
use App\Models\Assessment\Semester;
use App\Models\Assessment\Subject;
use App\Models\Assessment\TeachingAssignment;
use App\Models\GuruTendik;
use App\Models\Rombel;
use App\Models\User;
use App\Support\Admin\AdminModuleAccess;
use App\Support\AssessmentMaster\AssessmentMasterWorkbookImporter;
use Database\Seeders\InitialAdminSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Livewire\Livewire;
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

        $this->assertContains('Penilaian', User::navigationGroupOptions());
        $this->assertSame(
            'Penilaian',
            AdminModuleAccess::definition('penilaian')['group'],
        );
        $this->assertContains(
            AssessmentDashboard::class,
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
        $this->assertFalse(AstsInputScores::canAccess());
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
