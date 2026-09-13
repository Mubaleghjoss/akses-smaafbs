<?php

namespace Tests\Feature;

use App\Support\Assessment\Reporting\OnlineExamDemoReportMarker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OnlineExamDemoReportMarkerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('exam_question_sets', function (Blueprint $table): void {
            $table->id();
            $table->string('subject');
        });
        Schema::create('exam_schedules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('question_set_id');
            $table->string('exam_code');
            $table->string('class_name');
        });
        Schema::create('exam_student_tokens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id');
        });
        Schema::create('exam_attempts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('schedule_id');
            $table->unsignedBigInteger('student_token_id');
            $table->string('status');
            $table->decimal('final_score', 10, 2)->nullable();
        });
    }

    public function test_submitted_demo_attempt_adds_display_only_report_marker_without_changing_score(): void
    {
        DB::table('exam_question_sets')->insert(['id' => 1, 'subject' => 'Matematika']);
        DB::table('exam_schedules')->insert(['id' => 1, 'question_set_id' => 1, 'exam_code' => 'DEMO-UJIAN-MVP-2026', 'class_name' => 'X 1']);
        DB::table('exam_student_tokens')->insert(['id' => 1, 'student_id' => 42]);
        DB::table('exam_attempts')->insert(['schedule_id' => 1, 'student_token_id' => 1, 'status' => 'submitted', 'final_score' => 91]);

        $subjects = app(OnlineExamDemoReportMarker::class)->annotate([
            ['name' => 'Matematika', 'final_score' => '82.00'],
            ['name' => 'Bahasa Indonesia', 'final_score' => '88.00'],
        ], 42, 'X 1', 'asts');

        $this->assertSame('82.00', $subjects[0]['final_score']);
        $this->assertSame('Murni Ujian', data_get($subjects[0], 'online_exam_demo.label'));
        $this->assertArrayNotHasKey('online_exam_demo', $subjects[1]);
        $this->assertStringContainsString(
            'Murni Ujian',
            view('assessment.reports._document-asts', ['snapshot' => ['subjects' => $subjects]])->render(),
        );
    }

    public function test_marker_is_not_added_for_non_asts_report_or_non_demo_exam(): void
    {
        $subjects = app(OnlineExamDemoReportMarker::class)->annotate([
            ['name' => 'Matematika', 'final_score' => '82.00'],
        ], 42, 'X 1', 'asas');

        $this->assertArrayNotHasKey('online_exam_demo', $subjects[0]);
    }
}
