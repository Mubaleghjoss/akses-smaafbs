<?php

namespace Tests\Feature;

use App\Console\Commands\InstallAssessmentDefaults;
use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\Assessment\ReportTemplate;
use App\Models\Assessment\Semester;
use App\Models\Assessment\Subject;
use App\Models\User;
use App\Policies\Assessment\AssessmentPeriodAssignmentPolicy;
use App\Policies\Assessment\AssessmentPeriodPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class AssessmentFoundationTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();

        $migration = require database_path(
            'migrations/2026_07_31_080000_create_assessment_foundation_tables.php',
        );
        $migration->up();
        $reportStructureMigration = require database_path(
            'migrations/2026_07_31_120000_extend_assessment_report_structure.php',
        );
        $reportStructureMigration->up();
        (require database_path('migrations/2026_08_06_150000_add_assessment_subject_categories.php'))->up();
        (require database_path('migrations/2026_08_03_080000_add_stream_delivery_to_assessment_reports.php'))->up();
    }

    public function test_assessment_schema_is_additive_and_complete(): void
    {
        foreach ([
            'assessment_academic_years',
            'assessment_semesters',
            'assessment_subjects',
            'assessment_subject_categories',
            'assessment_teaching_assignments',
            'assessment_homeroom_assignments',
            'assessment_periods',
            'assessment_period_rombels',
            'assessment_period_students',
            'assessment_period_assignments',
            'assessment_period_homerooms',
            'assessment_schemes',
            'assessment_components',
            'assessment_scores',
            'assessment_student_subject_results',
            'assessment_homeroom_reports',
            'assessment_report_templates',
            'assessment_report_snapshots',
            'assessment_class_report_artifacts',
            'assessment_report_share_links',
            'assessment_audit_logs',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table.' seharusnya tersedia.');
        }

        $this->assertTrue(Schema::hasColumns('assessment_period_assignments', [
            'assessment_period_id',
            'teacher_id',
            'assessment_subject_id',
            'assessment_period_rombel_id',
            'status',
            'lock_version',
        ]));
        $this->assertTrue(Schema::hasColumns('assessment_scores', [
            'assessment_period_assignment_id',
            'assessment_period_student_id',
            'assessment_component_id',
            'source_result_id',
            'source_score_snapshot',
        ]));
        $this->assertTrue(Schema::hasColumn('assessment_schemes', 'source_rombel_id'));
        $this->assertTrue(Schema::hasColumns('assessment_subjects', [
            'report_group_code',
            'report_group_name',
            'report_group_sort_order',
            'sort_order',
        ]));
        $this->assertTrue(Schema::hasColumn('assessment_teaching_assignments', 'assessment_subject_category_id'));
        $this->assertDatabaseHas('assessment_subject_categories', ['code' => 'WAJIB', 'is_active' => true]);
        $this->assertDatabaseHas('assessment_subject_categories', ['code' => 'PILIHAN', 'is_active' => true]);
        $this->assertDatabaseHas('assessment_subject_categories', ['code' => 'UMUM-A-LEGACY', 'is_active' => false]);
        $this->assertTrue(Schema::hasColumns('assessment_period_assignments', [
            'subject_group_code_snapshot',
            'subject_group_name_snapshot',
            'subject_group_sort_order_snapshot',
            'subject_sort_order_snapshot',
        ]));
        $this->assertTrue(Schema::hasColumns('assessment_homeroom_reports', [
            'spiritual_predicate',
            'spiritual_description',
            'social_predicate',
            'social_description',
        ]));
        $this->assertTrue(Schema::hasColumns('assessment_report_snapshots', [
            'snapshot_checksum',
            'delivery_mode',
        ]));
        $this->assertTrue(Schema::hasColumn('assessment_class_report_artifacts', 'cache_expires_at'));
    }

    public function test_asts_and_asas_are_separate_but_duplicate_type_is_rejected(): void
    {
        [$year, $semester] = $this->createYearAndSemester();

        $asts = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $year->id,
            'assessment_semester_id' => $semester->id,
            'code' => 'ASTS-FOUNDATION',
            'name' => 'ASTS Foundation',
            'type' => AssessmentType::ASTS,
            'status' => AssessmentPeriodStatus::DRAFT,
        ]);
        $asas = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $year->id,
            'assessment_semester_id' => $semester->id,
            'code' => 'ASAS-FOUNDATION',
            'name' => 'ASAS Foundation',
            'type' => AssessmentType::ASAS,
            'status' => AssessmentPeriodStatus::DRAFT,
        ]);

        $this->assertNotSame($asts->id, $asas->id);
        $this->assertSame(AssessmentType::ASTS, $asts->type);
        $this->assertSame(AssessmentType::ASAS, $asas->type);

        $this->expectException(QueryException::class);

        AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $year->id,
            'assessment_semester_id' => $semester->id,
            'code' => 'ASTS-DUPLICATE',
            'name' => 'ASTS Duplicate',
            'type' => AssessmentType::ASTS,
            'status' => AssessmentPeriodStatus::DRAFT,
        ]);
    }

    public function test_install_defaults_is_idempotent_and_never_removes_custom_permissions(): void
    {
        Permission::findOrCreate('penilaian.custom-school-rule', 'web');
        $customRole = Role::findOrCreate('guru_mapel', 'web');
        $customRole->givePermissionTo('penilaian.custom-school-rule');

        $this->assertSame(0, Artisan::call('assessment:install-defaults'));
        $this->assertSame(0, Artisan::call('assessment:install-defaults'));

        $this->assertSame(4, ReportTemplate::query()->count());
        $this->assertSame(
            count(InstallAssessmentDefaults::PERMISSIONS),
            Permission::query()->whereIn('name', InstallAssessmentDefaults::PERMISSIONS)->count(),
        );
        $this->assertTrue($customRole->fresh()->hasPermissionTo('penilaian.custom-school-rule'));
        $this->assertTrue(
            Role::findByName('kurikulum', 'web')->hasPermissionTo('penilaian.period.manage'),
        );

        // Kebijakan sekolah (2026-08): kurikulum dapat melakukan SEMUA aksi guru
        // dan wali kelas, sebab kurikulum yang menggantikan mengisi saat guru
        // berhalangan. Sebelumnya kurikulum sengaja TIDAK diberi input/submit.
        $this->assertTrue(
            Role::findByName('kurikulum', 'web')->hasPermissionTo('penilaian.input'),
        );
        $this->assertTrue(
            Role::findByName('kurikulum', 'web')->hasPermissionTo('penilaian.submit'),
        );
        $this->assertTrue(
            Role::findByName('kurikulum', 'web')->hasPermissionTo('penilaian.homeroom'),
        );

        // Wali kelas & kepala sekolah boleh MENCETAK rapor, tetapi tidak boleh
        // memverifikasi maupun menerbitkan.
        $this->assertTrue(
            Role::findByName('wali_kelas', 'web')->hasPermissionTo('penilaian.report.generate'),
        );
        $this->assertFalse(
            Role::findByName('wali_kelas', 'web')->hasPermissionTo('penilaian.verify'),
        );
        $this->assertFalse(
            Role::findByName('wali_kelas', 'web')->hasPermissionTo('penilaian.publish'),
        );
        $this->assertTrue(
            Role::findByName('kepala_sekolah', 'web')->hasPermissionTo('penilaian.report.generate'),
        );
        $this->assertFalse(
            Role::findByName('kepala_sekolah', 'web')->hasPermissionTo('penilaian.publish'),
        );

        // Guru mapel tetap tidak boleh mencetak rapor.
        $this->assertFalse(
            Role::findByName('guru_mapel', 'web')->hasPermissionTo('penilaian.report.generate'),
        );
    }

    public function test_policies_enforce_teacher_scope_and_state_transitions(): void
    {
        Artisan::call('assessment:install-defaults');

        [$year, $semester] = $this->createYearAndSemester();
        $subject = Subject::query()->create([
            'code' => 'MAT',
            'name' => 'Matematika',
            'is_active' => true,
        ]);
        $period = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $year->id,
            'assessment_semester_id' => $semester->id,
            'code' => 'ASTS-POLICY',
            'name' => 'ASTS Policy',
            'type' => AssessmentType::ASTS,
            'status' => AssessmentPeriodStatus::OPEN,
        ]);
        $rombel = AssessmentPeriodRombel::query()->create([
            'assessment_period_id' => $period->id,
            'source_rombel_id' => 101,
            'rombel_name_snapshot' => 'XI 1',
            'grade_level' => 'XI',
            'is_active' => true,
        ]);
        $assignment = AssessmentPeriodAssignment::query()->create([
            'assessment_period_id' => $period->id,
            'assessment_period_rombel_id' => $rombel->id,
            'teacher_id' => 501,
            'assessment_subject_id' => $subject->id,
            'teacher_name_snapshot' => 'Guru Matematika',
            'subject_name_snapshot' => 'Matematika',
            'rombel_name_snapshot' => 'XI 1',
            'status' => AssignmentStatus::DRAFT,
        ]);

        $teacher = $this->createUser('teacher-policy', 501, 'guru_mapel');
        $otherTeacher = $this->createUser('other-teacher-policy', 502, 'guru_mapel');
        $curriculum = $this->createUser('curriculum-policy', null, 'kurikulum');

        $this->assertInstanceOf(
            AssessmentPeriodPolicy::class,
            Gate::getPolicyFor(AssessmentPeriod::class),
        );
        $this->assertInstanceOf(
            AssessmentPeriodAssignmentPolicy::class,
            Gate::getPolicyFor(AssessmentPeriodAssignment::class),
        );
        $this->assertTrue($teacher->can('updateScores', $assignment));
        $this->assertTrue($teacher->can('submit', $assignment));
        $this->assertFalse($otherTeacher->can('updateScores', $assignment));
        $this->assertFalse($curriculum->can('updateScores', $assignment));

        $assignment->forceFill(['status' => AssignmentStatus::SUBMITTED])->save();

        $this->assertTrue($curriculum->can('verify', $assignment));
        $this->assertFalse($teacher->can('verify', $assignment));

        $period->forceFill(['status' => AssessmentPeriodStatus::ENTRY_CLOSED])->save();
        $this->assertTrue($curriculum->can('startVerification', $period));
        $this->assertFalse($teacher->can('startVerification', $period));

        $period->forceFill(['status' => AssessmentPeriodStatus::LOCKED])->save();
        $this->assertFalse($teacher->can('updateScores', $assignment));
    }

    /**
     * @return array{AcademicYear, Semester}
     */
    private function createYearAndSemester(): array
    {
        $year = AcademicYear::query()->create([
            'code' => '2026-2027',
            'name' => '2026/2027',
            'is_active' => true,
        ]);
        $semester = Semester::query()->create([
            'assessment_academic_year_id' => $year->id,
            'code' => 'ganjil',
            'name' => 'Ganjil',
            'is_active' => true,
        ]);

        return [$year, $semester];
    }

    private function createUser(string $username, ?int $teacherId, string $role): User
    {
        $user = User::query()->create([
            'name' => str($username)->headline()->toString(),
            'username' => $username,
            'email' => null,
            'password' => 'secret-password',
            'guru_tendik_id' => $teacherId,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
