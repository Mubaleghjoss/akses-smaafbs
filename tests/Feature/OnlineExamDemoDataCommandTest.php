<?php

namespace Tests\Feature;

use App\Models\Exam\Attempt;
use App\Models\Exam\Question;
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
            ->expectsOutputToContain('/ujian')
            ->assertSuccessful();

        $this->assertDatabaseHas('exam_schedules', ['exam_code' => 'DEMO-UJIAN-MVP-2026', 'class_name' => 'X 1']);
        $this->assertSame(4, Question::query()->count());
        $this->assertSame(3, StudentToken::query()->count());
        $this->assertSame(3, Attempt::query()->count());
        $this->assertDatabaseHas('exam_attempts', ['status' => 'submitted', 'final_score' => 10]);
        $this->assertDatabaseHas('exam_attempts', ['status' => 'verified', 'exit_count' => 1, 'offline_count' => 1]);
        $this->assertDatabaseMissing('exam_student_tokens', ['token_hash' => 'DM01-0001']);

        $this->artisan('exam:demo-data --apply')->assertSuccessful();
        $this->assertSame(1, Schedule::query()->where('exam_code', 'DEMO-UJIAN-MVP-2026')->count());
        $this->assertSame(4, Question::query()->count());
        $this->assertSame(3, StudentToken::query()->count());
        $this->assertSame(3, Attempt::query()->count());
    }
}
