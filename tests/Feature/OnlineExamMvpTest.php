<?php

namespace Tests\Feature;

use App\Models\Exam\AiSetting;
use App\Models\Exam\Answer;
use App\Models\Exam\Attempt;
use App\Models\Exam\Question;
use App\Models\Exam\QuestionSet;
use App\Models\Exam\Schedule;
use App\Models\Exam\StudentToken;
use App\Models\User;
use App\Services\Exam\ScoringService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class OnlineExamMvpTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootstrapUserAndPermissionTables();
        config(['assessment.enabled' => true]);
        Schema::create('assessment_periods', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('data_siswa', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('nama')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_04_15_000000_create_online_exam_mvp_tables.php'))->up();
    }

    public function test_ai_key_is_encrypted_and_not_exposed_by_serialization(): void
    {
        $setting = AiSetting::create(['provider' => 'openai-compatible', 'api_key' => 'secret-value', 'enabled' => true]);
        $this->assertStringNotContainsString('secret-value', (string) $setting->getRawOriginal('api_key'));
        $this->assertArrayNotHasKey('api_key', $setting->toArray());
    }

    public function test_guest_and_user_without_assessment_access_cannot_use_admin_exam_routes(): void
    {
        $this->post(route('admin.exam.question-sets.store'))->assertRedirect();

        $user = User::factory()->create(['username' => 'exam-no-access']);
        $this->actingAs($user)
            ->post(route('admin.exam.question-sets.store'), [])
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('admin.exam.ai.test'))
            ->assertForbidden();
    }

    public function test_authenticated_teacher_can_create_own_question_package(): void
    {
        $teacher = User::factory()->create(['username' => 'teacher-create', 'module_access_levels' => ['penilaian' => 'manage']]);
        $this->actingAs($teacher)->post(route('admin.exam.question-sets.store'), ['title' => 'ASTS Matematika', 'subject' => 'Matematika', 'exam_type' => 'ASTS', 'academic_year' => '2026/2027', 'semester' => 'Ganjil'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('exam_question_sets', ['teacher_id' => $teacher->id, 'title' => 'ASTS Matematika']);
    }

    public function test_public_verification_rejects_invalid_student_token_and_accepts_valid_unique_token(): void
    {
        [$schedule, $token, $attempt, $plainToken] = $this->fixtures();
        $payload = ['class_name' => 'X-A', 'student_name' => 'Siswa Uji', 'nisn' => '12345', 'birth_date' => '2010-01-02', 'exam_code' => $plainToken];
        $this->post(route('exam.verify'), [...$payload, 'exam_code' => 'ZZZZ-ZZZZ'])->assertSessionHasErrors('exam_code');
        $this->post(route('exam.verify'), $payload)->assertRedirect();
        $this->assertNotNull($token->fresh()->verified_at);
    }

    public function test_student_token_hash_is_not_serialized(): void
    {
        [, $token] = $this->fixtures();
        $this->assertArrayNotHasKey('token_hash', $token->toArray());
    }

    public function test_multiple_choice_and_exact_multiple_response_scoring(): void
    {
        [$schedule] = $this->fixtures();
        $pg = Question::create(['question_set_id' => $schedule->question_set_id, 'type' => 'multiple_choice', 'prompt' => '2+2', 'answer_key' => ['4'], 'weight' => 2]);
        $complex = Question::create(['question_set_id' => $schedule->question_set_id, 'type' => 'multiple_response', 'prompt' => 'Genap', 'answer_key' => ['2', '4'], 'weight' => 3]);
        $service = app(ScoringService::class);
        $this->assertSame(2.0, $service->score($pg, ['4'])['score']);
        $this->assertSame(3.0, $service->score($complex, ['4', '2'])['score']);
        $this->assertSame(0.0, $service->score($complex, ['2'])['score']);
    }

    public function test_emergency_export_excludes_answer_key_and_manual_essay_grade_is_stored(): void
    {
        [$schedule, $token, $attempt] = $this->fixtures();
        $question = Question::create(['question_set_id' => $schedule->question_set_id, 'type' => 'essay', 'prompt' => 'Jelaskan proses', 'answer_key' => ['KEY-MUST-NOT-LEAK'], 'rubric' => 'Rubrik', 'weight' => 5]);
        $answer = Answer::create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'answer' => ['Jawaban siswa']]);
        $this->withSession(['exam_attempt_id' => $attempt->id])->get(route('exam.emergency', $attempt->public_id))->assertOk()->assertDontSee('KEY-MUST-NOT-LEAK')->assertDontSee('Rubrik')->assertDontSee($token->token_hash)->assertSee('Jawaban siswa')->assertSee('Status attempt')->assertSee('Alasan ekspor')->assertSee('Hash integritas');
        $teacher = $schedule->questionSet->teacher;
        $this->actingAs($teacher)->post(route('admin.exam.answers.grade', $answer), ['manual_score' => 4, 'teacher_feedback' => 'Baik'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('exam_answers', ['id' => $answer->id, 'manual_score' => 4]);
    }

    public function test_exam_navigation_and_public_routes_exist(): void
    {
        $this->assertSame('/ujian', route('exam.index', absolute: false));
        $this->assertTrue(in_array(\App\Filament\Pages\Assessment\OnlineExamPage::class, \App\Support\Admin\AdminSchoolNavigation::classifiedClasses(), true));
    }

    private function fixtures(): array
    {
        $teacher = User::factory()->create(['username' => 'teacher-'.Str::lower(Str::random(8)), 'module_access_levels' => ['penilaian' => 'manage']]);
        $set = QuestionSet::create(['teacher_id' => $teacher->id, 'subject' => 'Matematika', 'exam_type' => 'ASTS', 'academic_year' => '2026/2027', 'semester' => 'Ganjil', 'title' => 'ASTS Matematika']);
        $schedule = Schedule::create(['question_set_id' => $set->id, 'created_by' => $teacher->id, 'class_name' => 'X-A', 'exam_code' => 'ASTS-XA', 'supervisor_code_hash' => Hash::make('awas123'), 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(), 'duration_minutes' => 60, 'is_active' => true]);
        $plainToken = 'ABCD-1234';
        $token = StudentToken::create(['schedule_id' => $schedule->id, 'student_name' => 'Siswa Uji', 'nisn' => '12345', 'birth_date' => '2010-01-02', 'class_name' => 'X-A', 'token_hash' => StudentToken::hashToken($plainToken)]);
        $attempt = Attempt::create(['public_id' => (string) Str::uuid(), 'schedule_id' => $schedule->id, 'student_token_id' => $token->id]);
        return [$schedule, $token, $attempt, $plainToken];
    }
}
