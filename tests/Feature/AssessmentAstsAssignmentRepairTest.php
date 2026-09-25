<?php

namespace Tests\Feature;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\AssessmentComponent;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\AssessmentScheme;
use App\Models\Assessment\AssessmentScore;
use App\Models\Assessment\Semester;
use App\Models\Assessment\StudentSubjectResult;
use App\Models\Assessment\Subject;
use App\Models\Assessment\SubjectCategory;
use App\Models\Assessment\TeachingAssignment;
use App\Models\GuruTendik;
use App\Models\Rombel;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\BootstrapsStudentAndTeacherTables;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class AssessmentAstsAssignmentRepairTest extends TestCase
{
    use BootstrapsStudentAndTeacherTables;
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootstrapStudentAndTeacherTables();
        $this->bootstrapUserAndPermissionTables();
        (require database_path('migrations/2026_07_31_080000_create_assessment_foundation_tables.php'))->up();
        (require database_path('migrations/2026_07_31_120000_extend_assessment_report_structure.php'))->up();
        (require database_path('migrations/2026_08_06_150000_add_assessment_subject_categories.php'))->up();
    }

    public function test_dry_run_and_expected_period_guard_do_not_change_assignment(): void
    {
        [$assignment, $other, $actor] = $this->context();
        $this->artisan('assessment:repair-asts-assignment', ['assignment' => $assignment->id, '--expected-period' => 999])
            ->expectsOutputToContain('expected-period tidak cocok')
            ->assertFailed();
        $this->assertDatabaseCount('assessment_scores', 2);
        $this->assertSame(AssignmentStatus::SUBMITTED, $assignment->fresh()->status);
        $this->assertSame(1, $other->scores()->count());
        $this->assertDatabaseCount('assessment_audit_logs', 0);
        $this->assertNotNull($actor);
    }

    public function test_apply_resets_only_target_and_creates_scoped_asts_scheme(): void
    {
        [$assignment, $other, $actor, $teaching] = $this->context();
        $this->artisan('assessment:repair-asts-assignment', [
            'assignment' => $assignment->id, '--apply' => true, '--actor' => $actor->id,
            '--reason' => 'Memperbaiki plotting dan komponen ASTS.', '--expected-period' => $assignment->assessment_period_id,
            '--expected-lock-version' => 7,
        ])->assertSuccessful();

        $this->assertSame(0, $assignment->scores()->count());
        $this->assertSame(0, $assignment->results()->count());
        $this->assertSame(1, $other->scores()->count());
        $fresh = $assignment->fresh();
        $this->assertSame(AssignmentStatus::DRAFT, $fresh->status);
        $this->assertSame(8, $fresh->lock_version);
        $this->assertSame($teaching->id, $fresh->source_teaching_assignment_id);
        $this->assertSame($teaching->teacher_id, $fresh->teacher_id);
        $scheme = AssessmentScheme::query()->where('assessment_period_rombel_id', $assignment->assessment_period_rombel_id)->where('assessment_subject_id', $assignment->assessment_subject_id)->firstOrFail();
        $this->assertSame(['UH1', 'UH2', 'UH3', 'ASTS_MURNI'], $scheme->components()->where('settings->is_active', true)->orderBy('sort_order')->pluck('code')->all());
        $this->assertDatabaseHas('assessment_audit_logs', ['event' => 'assignment.asts_repaired_and_scores_reset', 'actor_id' => $actor->id]);
    }

    /** @return array{AssessmentPeriodAssignment, AssessmentPeriodAssignment, User, TeachingAssignment} */
    private function context(): array
    {
        $actor = User::query()->create(['name' => 'Admin Repair', 'username' => 'admin-repair', 'password' => 'x']);
        $year = AcademicYear::query()->create(['code' => 'REPAIR', 'name' => 'Repair']);
        $semester = Semester::query()->create(['assessment_academic_year_id' => $year->id, 'code' => 'REPAIR-1', 'name' => 'Repair 1']);
        $period = AssessmentPeriod::query()->create(['assessment_academic_year_id' => $year->id, 'assessment_semester_id' => $semester->id, 'code' => 'ASTS-REPAIR', 'name' => 'ASTS Repair', 'type' => AssessmentType::ASTS, 'status' => AssessmentPeriodStatus::OPEN, 'created_by' => $actor->id]);
        $teacher = GuruTendik::query()->create(['nama' => 'Guru Baru', 'status' => 'aktif']);
        $oldTeacher = GuruTendik::query()->create(['nama' => 'Guru Lama', 'status' => 'aktif']);
        $otherTeacher = GuruTendik::query()->create(['nama' => 'Guru Lain', 'status' => 'aktif']);
        $rombel = Rombel::query()->create(['nama' => 'XI Repair', 'is_active' => true]);
        $periodRombel = AssessmentPeriodRombel::query()->create(['assessment_period_id' => $period->id, 'source_rombel_id' => $rombel->id, 'rombel_name_snapshot' => $rombel->nama, 'is_active' => true]);
        $subject = Subject::query()->create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'is_active' => true]);
        $category = SubjectCategory::query()->firstOrCreate(['code' => 'WAJIB'], ['name' => 'Wajib', 'type' => SubjectCategory::TYPE_WAJIB, 'sort_order' => 1, 'is_active' => true]);
        $teaching = TeachingAssignment::query()->create(['assessment_semester_id' => $semester->id, 'assessment_subject_id' => $subject->id, 'assessment_subject_category_id' => $category->id, 'teacher_id' => $teacher->id, 'rombel_id' => $rombel->id, 'teacher_name_snapshot' => $teacher->nama, 'subject_name_snapshot' => $subject->name, 'rombel_name_snapshot' => $rombel->nama, 'is_active' => true]);
        $scheme = AssessmentScheme::query()->create(['assessment_period_id' => $period->id, 'name' => 'Default', 'rounding_precision' => 2, 'minimum_score' => 0, 'maximum_score' => 100, 'is_active' => true]);
        $component = AssessmentComponent::query()->create(['assessment_scheme_id' => $scheme->id, 'code' => 'OLD', 'name' => 'Old', 'weight' => 100, 'maximum_score' => 100, 'is_required' => true, 'sort_order' => 1, 'score_source' => 'manual', 'settings' => ['is_active' => true]]);
        $student = AssessmentPeriodStudent::query()->create(['assessment_period_id' => $period->id, 'assessment_period_rombel_id' => $periodRombel->id, 'student_id' => 101, 'student_name_snapshot' => 'Siswa Repair', 'rombel_name_snapshot' => $rombel->nama, 'is_active' => true]);
        $makeAssignment = fn (GuruTendik $assignedTeacher, string $status, int $lock) => AssessmentPeriodAssignment::query()->create(['assessment_period_id' => $period->id, 'assessment_period_rombel_id' => $periodRombel->id, 'teacher_id' => $assignedTeacher->id, 'assessment_subject_id' => $subject->id, 'teacher_name_snapshot' => $assignedTeacher->nama, 'subject_name_snapshot' => $subject->name, 'rombel_name_snapshot' => $rombel->nama, 'status' => $status, 'lock_version' => $lock, 'subject_group_code_snapshot' => 'OLD', 'subject_group_name_snapshot' => 'Old', 'subject_group_sort_order_snapshot' => 9, 'subject_sort_order_snapshot' => 1]);
        $assignment = $makeAssignment($oldTeacher, AssignmentStatus::SUBMITTED->value, 7);
        $other = $makeAssignment($otherTeacher, AssignmentStatus::DRAFT->value, 1);
        // The second assignment is deliberately a different teacher scope; it proves score deletion remains target-only.
        foreach ([$assignment, $other] as $row) {
            AssessmentScore::query()->create(['assessment_period_assignment_id' => $row->id, 'assessment_period_student_id' => $student->id, 'assessment_component_id' => $component->id, 'score' => 80, 'source' => 'manual']);
        }
        StudentSubjectResult::query()->create(['assessment_period_id' => $period->id, 'assessment_period_student_id' => $student->id, 'assessment_period_assignment_id' => $assignment->id, 'final_score' => 80]);

        return [$assignment, $other, $actor, $teaching];
    }
}
