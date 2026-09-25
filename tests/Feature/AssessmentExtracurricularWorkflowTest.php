<?php

namespace Tests\Feature;

use App\Enums\Assessment\AssessmentType;
use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\AssessmentExtracurricular;
use App\Models\Assessment\AssessmentExtracurricularParticipant;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\Semester;
use App\Models\User;
use App\Support\Assessment\AssessmentExtracurricularReportResolver;
use App\Support\Assessment\AssessmentExtracurricularWorkflow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AssessmentExtracurricularWorkflowTest extends TestCase
{
    private User $admin;
    private AssessmentPeriod $period;
    private AssessmentPeriodStudent $student;

    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('guru_tendik_id')->nullable();
            $table->json('module_access_levels')->nullable();
        });
        (require database_path('migrations/2026_01_12_111708_create_permission_tables.php'))->up();
        (require database_path('migrations/2026_07_31_080000_create_assessment_foundation_tables.php'))->up();
        (require database_path('migrations/2026_08_30_090000_create_assessment_extracurricular_tables.php'))->up();

        Role::findOrCreate('admin', 'web');
        $this->admin = User::query()->create(['name' => 'Admin', 'username' => 'admin-ekskul', 'email' => 'admin-ekskul@test.local', 'password' => bcrypt('password')]);
        $this->admin->assignRole('admin');
        $year = AcademicYear::query()->create(['code' => '2627', 'name' => '2026/2027']);
        $semester = Semester::query()->create(['assessment_academic_year_id' => $year->id, 'code' => 'GANJIL', 'name' => 'Ganjil']);
        $this->period = AssessmentPeriod::query()->create(['assessment_academic_year_id' => $year->id, 'assessment_semester_id' => $semester->id, 'code' => 'ASTS-EKSKUL-TEST', 'name' => 'ASTS Test', 'type' => AssessmentType::ASTS, 'created_by' => $this->admin->id]);
        $rombel = AssessmentPeriodRombel::query()->create(['assessment_period_id' => $this->period->id, 'source_rombel_id' => 10, 'rombel_name_snapshot' => 'X 1']);
        $this->student = AssessmentPeriodStudent::query()->create(['assessment_period_id' => $this->period->id, 'assessment_period_rombel_id' => $rombel->id, 'student_id' => 100, 'student_name_snapshot' => 'Siswa Ekskul', 'rombel_name_snapshot' => 'X 1', 'is_active' => true]);
    }

    public function test_verified_teacher_score_overrides_manual_and_unverified_score_keeps_fallback(): void
    {
        $activity = AssessmentExtracurricular::query()->create(['assessment_period_id' => $this->period->id, 'name' => 'Futsal', 'created_by' => $this->admin->id]);
        $participant = AssessmentExtracurricularParticipant::query()->create(['assessment_extracurricular_id' => $activity->id, 'assessment_period_student_id' => $this->student->id, 'assessment_period_rombel_id' => $this->student->assessment_period_rombel_id]);
        $manual = [['name' => 'Pramuka', 'description' => 'B']];
        $resolver = app(AssessmentExtracurricularReportResolver::class);

        $manualResolved = $resolver->resolve($this->student, $manual);
        $this->assertSame('Pramuka', $manualResolved[0]['name']);
        $this->assertSame('manual_walas', $manualResolved[0]['source']);
        $score = app(AssessmentExtracurricularWorkflow::class)->save($this->admin, $participant, 'A');
        $this->assertSame('Pramuka', $resolver->resolve($this->student, $manual)[0]['name']);
        app(AssessmentExtracurricularWorkflow::class)->submit($this->admin, $participant->fresh('score'));
        $this->assertSame('Pramuka', $resolver->resolve($this->student, $manual)[0]['name']);
        app(AssessmentExtracurricularWorkflow::class)->verify($this->admin, $score->fresh());

        $resolved = $resolver->resolve($this->student, $manual);
        $this->assertSame([['name' => 'Futsal', 'predicate' => 'A', 'description' => 'A', 'source' => 'guru_ekskul']], $resolved);
    }

    public function test_unassigned_user_cannot_edit_participant_score(): void
    {
        $activity = AssessmentExtracurricular::query()->create(['assessment_period_id' => $this->period->id, 'name' => 'Tahfidz', 'created_by' => $this->admin->id]);
        $participant = AssessmentExtracurricularParticipant::query()->create(['assessment_extracurricular_id' => $activity->id, 'assessment_period_student_id' => $this->student->id]);
        $outsider = User::query()->create(['name' => 'Guru Lain', 'username' => 'guru-lain-ekskul', 'email' => 'guru-lain@test.local', 'password' => bcrypt('password')]);

        $this->expectException(HttpException::class);
        app(AssessmentExtracurricularWorkflow::class)->save($outsider, $participant, 'B');
    }

    public function test_demo_command_is_dry_run_by_default_and_replaces_only_marker_rows(): void
    {
        $teacher = User::query()->create(['name' => 'Guru Demo', 'username' => 'guru-demo-ekskul', 'email' => 'guru-demo@test.local', 'password' => bcrypt('password'), 'guru_tendik_id' => 7]);
        AssessmentExtracurricular::query()->create(['assessment_period_id' => $this->period->id, 'name' => 'Ekskul Nyata', 'code' => 'REAL']);

        $this->artisan('assessment:demo-extracurricular-asts', ['--period' => $this->period->id])->assertSuccessful();
        $this->assertDatabaseCount('assessment_extracurriculars', 1);
        $this->artisan('assessment:demo-extracurricular-asts', ['--period' => $this->period->id, '--apply' => true])->assertSuccessful();
        $this->artisan('assessment:demo-extracurricular-asts', ['--period' => $this->period->id, '--apply' => true])->assertSuccessful();

        $this->assertDatabaseHas('assessment_extracurriculars', ['code' => 'REAL']);
        $this->assertSame(3, AssessmentExtracurricular::query()->where('code', 'like', 'DEMO-EKSKUL-%')->count());
    }
}
