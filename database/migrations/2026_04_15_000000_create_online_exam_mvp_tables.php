<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_ai_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('openai-compatible');
            $table->string('base_url')->nullable();
            $table->string('model')->nullable();
            $table->text('api_key')->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('daily_limit')->default(0);
            $table->unsignedInteger('per_request_limit')->default(10);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('exam_question_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject');
            $table->string('exam_type', 30);
            $table->string('academic_year', 20);
            $table->string('semester', 20);
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->index(['teacher_id', 'status']);
        });

        Schema::create('exam_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_set_id')->constrained('exam_question_sets')->cascadeOnDelete();
            $table->string('type', 30);
            $table->text('prompt');
            $table->string('image_path')->nullable();
            $table->json('options')->nullable();
            $table->json('answer_key')->nullable();
            $table->decimal('weight', 8, 2)->default(1);
            $table->string('cognitive_level', 10)->default('MOTS');
            $table->text('explanation')->nullable();
            $table->text('rubric')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['question_set_id', 'position']);
        });

        Schema::create('exam_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_set_id')->constrained('exam_question_sets')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('class_name');
            $table->string('exam_code', 40)->unique();
            $table->string('supervisor_code_hash');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('duration_minutes')->default(90);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('exam_student_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('schedule_id')->constrained('exam_schedules')->cascadeOnDelete();
            $table->integer('student_id')->nullable();
            $table->foreign('student_id')->references('id')->on('data_siswa')->nullOnDelete();
            $table->string('student_name');
            $table->string('nisn', 30)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('class_name');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['schedule_id', 'student_id']);
        });

        Schema::create('exam_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('schedule_id')->constrained('exam_schedules')->cascadeOnDelete();
            $table->foreignId('student_token_id')->constrained('exam_student_tokens')->cascadeOnDelete();
            $table->string('status', 20)->default('verified');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('exit_count')->default(0);
            $table->unsignedInteger('offline_count')->default(0);
            $table->decimal('auto_score', 8, 2)->default(0);
            $table->decimal('essay_score', 8, 2)->default(0);
            $table->decimal('final_score', 8, 2)->nullable();
            $table->string('grading_status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['schedule_id', 'student_token_id']);
        });

        Schema::create('exam_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('exam_questions')->cascadeOnDelete();
            $table->json('answer')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('auto_score', 8, 2)->default(0);
            $table->decimal('manual_score', 8, 2)->nullable();
            $table->text('teacher_feedback')->nullable();
            $table->timestamp('saved_at')->nullable();
            $table->timestamps();
            $table->unique(['attempt_id', 'question_id']);
        });

        Schema::create('exam_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->string('type', 50);
            $table->json('metadata')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['attempt_id', 'occurred_at']);
        });

        Schema::create('exam_emergency_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->string('content_hash', 64);
            $table->timestamp('exported_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_emergency_exports');
        Schema::dropIfExists('exam_events');
        Schema::dropIfExists('exam_answers');
        Schema::dropIfExists('exam_attempts');
        Schema::dropIfExists('exam_student_tokens');
        Schema::dropIfExists('exam_schedules');
        Schema::dropIfExists('exam_questions');
        Schema::dropIfExists('exam_question_sets');
        Schema::dropIfExists('exam_ai_settings');
    }
};
