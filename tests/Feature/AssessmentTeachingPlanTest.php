<?php

namespace Tests\Feature;

use App\Actions\Assessment\SyncOpenPeriodSubjectAssignmentsAction;
use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\Assessment\Semester;
use App\Models\Assessment\Subject;
use App\Models\Assessment\SubjectCategory;
use App\Models\Assessment\TeachingAssignment;
use App\Models\GuruTendik;
use App\Models\Rombel;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\BootstrapsStudentAndTeacherTables;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class AssessmentTeachingPlanTest extends TestCase
{
    use BootstrapsStudentAndTeacherTables;
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapStudentAndTeacherTables();
        $this->bootstrapUserAndPermissionTables();
        config(['assessment.enabled' => true]);
        (require database_path('migrations/2026_07_31_080000_create_assessment_foundation_tables.php'))->up();
        (require database_path('migrations/2026_07_31_120000_extend_assessment_report_structure.php'))->up();
        (require database_path('migrations/2026_08_06_150000_add_assessment_subject_categories.php'))->up();

        $year = AcademicYear::query()->create([
            'code' => '2026-2027',
            'name' => 'Tahun Pelajaran 2026/2027',
            'is_active' => true,
        ]);
        Semester::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'code' => '2026-2027-GANJIL',
            'name' => 'Semester Ganjil',
            'is_active' => true,
        ]);

        foreach (['X 1', 'X 2', 'XI 1', 'XI 2', 'XII 1', 'XII 2', 'XII 3'] as $className) {
            Rombel::query()->create(['nama' => $className, 'is_active' => true]);
        }

        $names = [
            'Ahmad Tri Anggoro, S.T.', 'Aisyah Sekar Tri Wardani, S.Pd.', 'Fitri Nurfadhilah, S.Pd.',
            'Khoiriyah, S.Pd.', 'Kholifin Hilman Suharno, S.Pd.', 'Komariyah, S.Si.',
            'M. Fandakir, S.Pd.', 'Menik Putri Lestari, S.T.', 'M. Zahki Maulana, S.H.',
            'Mulky Fauzan, S.T., M.M.', 'Nurul Afifah, S.Pd.', 'Nurul Izzah Nisfulaily, S.Pd.',
        ];
        foreach ($names as $index => $name) {
            $teacher = GuruTendik::query()->create(['nama' => $name, 'jenis_ptk' => 'Guru', 'status' => 'aktif']);
            User::query()->create([
                'name' => $name,
                'username' => 'guru-rencana-'.($index + 1),
                'password' => 'secret123',
                'guru_tendik_id' => $teacher->getKey(),
            ]);
        }
    }

    public function test_preview_is_read_only_and_apply_is_atomic_idempotent_with_assignment_categories(): void
    {
        $this->artisan('assessment:teaching-plan-2026')
            ->expectsOutputToContain('Mode preview')
            ->assertSuccessful();
        $this->assertSame(0, Subject::query()->count());
        $this->assertSame(0, TeachingAssignment::query()->count());

        $semester = Semester::query()->where('code', '2026-2027-GANJIL')->firstOrFail();
        $oldTeacher = GuruTendik::query()->create(['nama' => 'Putra Kamulyan', 'jenis_ptk' => 'Guru', 'status' => 'aktif']);
        $oldSubject = Subject::query()->create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'is_active' => true]);
        $oldAssignment = TeachingAssignment::query()->create([
            'assessment_semester_id' => $semester->getKey(),
            'assessment_subject_id' => $oldSubject->getKey(),
            'assessment_subject_category_id' => SubjectCategory::query()->where('code', 'UMUM-A-LEGACY')->value('id'),
            'teacher_id' => $oldTeacher->getKey(),
            'rombel_id' => Rombel::query()->where('nama', 'X 1')->value('id'),
            'teacher_name_snapshot' => $oldTeacher->nama,
            'subject_name_snapshot' => $oldSubject->name,
            'rombel_name_snapshot' => 'X 1',
            'is_active' => true,
        ]);

        $this->artisan('assessment:teaching-plan-2026', ['--apply' => true])
            ->expectsOutputToContain('Selesai:')
            ->assertSuccessful();

        $this->assertSame(19, Subject::query()->count());
        $this->assertSame(99, TeachingAssignment::query()->where('is_active', true)->count());
        $this->assertFalse($oldAssignment->fresh()->is_active);
        $this->assertSame(3, SubjectCategory::query()->count());
        $this->assertSame(2, SubjectCategory::query()->where('is_active', true)->count());

        $bin = Subject::query()->where('code', 'BIN')->firstOrFail();
        $x1 = Rombel::query()->where('nama', 'X 1')->firstOrFail();
        $xi1 = Rombel::query()->where('nama', 'XI 1')->firstOrFail();
        $this->assertDatabaseHas('assessment_teaching_assignments', [
            'assessment_semester_id' => $semester->getKey(),
            'assessment_subject_id' => $bin->getKey(),
            'rombel_id' => $x1->getKey(),
            'assessment_subject_category_id' => SubjectCategory::query()->where('code', 'PILIHAN')->value('id'),
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('assessment_teaching_assignments', [
            'assessment_subject_id' => $bin->getKey(),
            'rombel_id' => $xi1->getKey(),
            'assessment_subject_category_id' => SubjectCategory::query()->where('code', 'WAJIB')->value('id'),
            'is_active' => true,
        ]);

        $firstCount = TeachingAssignment::query()->count();
        $this->artisan('assessment:teaching-plan-2026', ['--apply' => true])->assertSuccessful();
        $this->assertSame($firstCount, TeachingAssignment::query()->count());
        $this->assertSame(99, TeachingAssignment::query()->where('is_active', true)->count());
    }

    public function test_apply_stops_before_writing_when_one_linked_teacher_is_missing(): void
    {
        User::query()->where('username', 'guru-rencana-12')->delete();

        $this->artisan('assessment:teaching-plan-2026', ['--apply' => true])
            ->expectsOutputToContain('harus tepat satu akun tertaut')
            ->assertFailed();

        $this->assertSame(0, Subject::query()->count());
        $this->assertSame(0, TeachingAssignment::query()->count());
    }

    public function test_explicit_open_period_sync_moves_teacher_and_increments_lock_without_touching_locked_periods(): void
    {
        $this->artisan('assessment:teaching-plan-2026', ['--apply' => true])->assertSuccessful();
        Role::findOrCreate('admin', 'web');
        $actor = User::query()->create([
            'name' => 'Admin Sinkron Plotting',
            'username' => 'admin-sinkron-plotting',
            'password' => 'secret123',
        ]);
        $actor->assignRole('admin');
        $semester = Semester::query()->where('code', '2026-2027-GANJIL')->firstOrFail();
        $subject = Subject::query()->where('code', 'FIS')->firstOrFail();
        $rombel = Rombel::query()->where('nama', 'X 1')->firstOrFail();
        $desiredMaster = TeachingAssignment::query()->where([
            'assessment_semester_id' => $semester->getKey(),
            'assessment_subject_id' => $subject->getKey(),
            'rombel_id' => $rombel->getKey(),
            'is_active' => true,
        ])->firstOrFail();
        $oldTeacher = GuruTendik::query()->create(['nama' => 'Guru Lama Fisika', 'jenis_ptk' => 'Guru', 'status' => 'aktif']);
        $year = $semester->academicYear;
        $period = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'assessment_semester_id' => $semester->getKey(),
            'code' => 'ASTS-SYNC-PLOTTING',
            'name' => 'ASTS Sinkron Plotting',
            'type' => AssessmentType::ASTS,
            'status' => AssessmentPeriodStatus::OPEN,
            'settings' => ['rombel_ids' => [$rombel->getKey()]],
            'created_by' => $actor->getKey(),
        ]);
        $periodRombel = AssessmentPeriodRombel::query()->create([
            'assessment_period_id' => $period->getKey(),
            'source_rombel_id' => $rombel->getKey(),
            'rombel_name_snapshot' => $rombel->nama,
            'grade_level' => 'X',
            'is_active' => true,
        ]);
        $periodAssignment = AssessmentPeriodAssignment::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $periodRombel->getKey(),
            'assessment_subject_id' => $subject->getKey(),
            'teacher_id' => $oldTeacher->getKey(),
            'teacher_name_snapshot' => $oldTeacher->nama,
            'subject_name_snapshot' => $subject->name,
            'subject_group_code_snapshot' => 'UMUM-A-LEGACY',
            'subject_group_name_snapshot' => 'Kelompok A (Umum)',
            'subject_group_sort_order_snapshot' => 900,
            'subject_sort_order_snapshot' => $subject->sort_order,
            'rombel_name_snapshot' => $rombel->nama,
            'status' => AssignmentStatus::DRAFT,
            'lock_version' => 1,
        ]);

        $summary = app(SyncOpenPeriodSubjectAssignmentsAction::class)->execute($actor, $subject, $period);
        $this->assertSame(['created' => 0, 'updated' => 1, 'deleted' => 0], $summary);
        $this->assertSame($desiredMaster->teacher_id, $periodAssignment->fresh()->teacher_id);
        $this->assertSame(2, $periodAssignment->fresh()->lock_version);
        $this->assertSame('PILIHAN', $periodAssignment->fresh()->subject_group_code_snapshot);

        $period->forceFill(['status' => AssessmentPeriodStatus::LOCKED])->save();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(SyncOpenPeriodSubjectAssignmentsAction::class)->execute($actor, $subject, $period->fresh());
    }
}
