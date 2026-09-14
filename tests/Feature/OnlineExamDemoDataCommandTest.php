<?php

namespace Tests\Feature;

use App\Models\Exam\Attempt;
use App\Models\Exam\Question;
use App\Models\Exam\QuestionSet;
use App\Models\Exam\Schedule;
use App\Models\Exam\StudentToken;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class OnlineExamDemoDataCommandTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootstrapUserAndPermissionTables();
        Schema::create('assessment_periods', function (Blueprint $table): void { $table->id(); });
        Schema::create('data_siswa', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->string('nisn')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('rombel_saat_ini')->nullable();
            $table->string('status')->default('aktif');
            $table->timestamps();
        });
        (require database_path('migrations/2026_04_15_000000_create_online_exam_mvp_tables.php'))->up();
        User::factory()->create(['username' => 'demo-exam-owner']);
        foreach (range(1, 3) as $id) {
            
            \Illuminate\Support\Facades\DB::table('data_siswa')->insert(['nama' => 'Siswa Demo '.$id, 'nisn' => '100'.$id, 'tanggal_lahir' => '2010-01-0'.$id, 'rombel_saat_ini' => 'X 1', 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function test_command_creates_repeatable_complete_online_exam_demo_data(): void
    {
        $this->artisan('exam:demo-data --apply')
            ->expectsOutputToContain('Token demo Siswa Demo 1')
            ->expectsOutputToContain('Token testing siap dikerjakan Siswa Demo 3')
            ->expectsOutputToContain('/ujian')
            ->assertSuccessful();

        $this->assertDatabaseHas('exam_schedules', ['exam_code' => 'DEMO-UJIAN-MVP-2026', 'class_name' => 'X 1']);
        $this->assertSame(4, Question::query()->count());
        $this->assertSame(4, StudentToken::query()->count());
        $this->assertSame(4, Attempt::query()->count());
        $this->assertDatabaseHas('exam_attempts', ['status' => 'submitted', 'final_score' => 10]);
        $this->assertDatabaseHas('exam_attempts', ['status' => 'verified', 'exit_count' => 1, 'offline_count' => 1]);
        $this->assertDatabaseMissing('exam_student_tokens', ['token_hash' => 'DM01-0001']);

        $this->artisan('exam:demo-data --apply')->assertSuccessful();
        $this->assertSame(1, Schedule::query()->where('exam_code', 'DEMO-UJIAN-MVP-2026')->count());
        $this->assertSame(4, Question::query()->count());
        $this->assertSame(4, StudentToken::query()->count());
        $this->assertSame(4, Attempt::query()->count());
    }

    public function test_command_cleans_up_legacy_demo_question_sets_with_marker(): void
    {
        $teacher = User::query()->first();
        $legacySet = QuestionSet::query()->create([
            'teacher_id' => $teacher->id,
            'subject' => 'MATEMATIKA',
            'exam_type' => 'ASTS',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'title' => '[DEMO-UJIAN-MVP] MATEMATIKA: Operasi Hitung',
            'status' => 'published',
        ]);
        Question::query()->create([
            'question_set_id' => $legacySet->id,
            'type' => 'multiple_choice',
            'prompt' => '1 + 1 = ?',
            'options' => ['2', '3'],
            'answer_key' => ['2'],
            'weight' => 2,
            'cognitive_level' => 'LOTS',
            'position' => 1,
        ]);
        Schedule::query()->create([
            'question_set_id' => $legacySet->id,
            'created_by' => $teacher->id,
            'class_name' => 'X 1',
            'exam_code' => 'DEMO-UJIAN-MVP-2026',
            'supervisor_code_hash' => bcrypt('DEMO-AWAS'),
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
            'duration_minutes' => 90,
            'is_active' => true,
        ]);

        $this->artisan('exam:demo-data --apply')->assertSuccessful();

        $this->assertDatabaseMissing('exam_question_sets', ['id' => $legacySet->id]);
        $this->assertDatabaseMissing('exam_questions', ['prompt' => '1 + 1 = ?']);
        $this->assertSame(1, QuestionSet::query()->count());
        $this->assertSame(4, Question::query()->count());
    }
}
