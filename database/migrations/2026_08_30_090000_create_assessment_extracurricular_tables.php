<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_extracurriculars', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_period_id')->constrained('assessment_periods')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 60)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
            $table->unique(['assessment_period_id', 'name'], 'assessment_extracurricular_period_name_unique');
            $table->unique(['assessment_period_id', 'code'], 'assessment_extracurricular_period_code_unique');
        });

        Schema::create('assessment_extracurricular_teachers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('assessment_extracurricular_id');
            $table->unsignedBigInteger('teacher_id')->index();
            $table->timestamps();
            $table->foreign('assessment_extracurricular_id', 'assess_ex_teachers_ex_fk')
                ->references('id')
                ->on('assessment_extracurriculars')
                ->cascadeOnDelete();
            $table->unique(['assessment_extracurricular_id', 'teacher_id'], 'assessment_extracurricular_teacher_unique');
        });

        Schema::create('assessment_extracurricular_participants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('assessment_extracurricular_id');
            $table->unsignedBigInteger('assessment_period_student_id');
            $table->unsignedBigInteger('assessment_period_rombel_id')->nullable();
            $table->string('source', 30)->default('manual')->index();
            $table->timestamps();
            $table->foreign('assessment_extracurricular_id', 'assess_ex_participants_ex_fk')
                ->references('id')
                ->on('assessment_extracurriculars')
                ->cascadeOnDelete();
            $table->foreign('assessment_period_student_id', 'assess_ex_participants_student_fk')
                ->references('id')
                ->on('assessment_period_students')
                ->cascadeOnDelete();
            $table->foreign('assessment_period_rombel_id', 'assess_ex_participants_rombel_fk')
                ->references('id')
                ->on('assessment_period_rombels')
                ->nullOnDelete();
            $table->unique(['assessment_extracurricular_id', 'assessment_period_student_id'], 'assessment_extracurricular_participant_unique');
        });

        Schema::create('assessment_extracurricular_scores', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('assessment_extracurricular_participant_id');
            $table->string('predicate', 1)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->dateTime('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable()->index();
            $table->dateTime('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable()->index();
            $table->dateTime('returned_at')->nullable();
            $table->unsignedBigInteger('returned_by')->nullable()->index();
            $table->text('returned_reason')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->foreign('assessment_extracurricular_participant_id', 'assess_ex_scores_participant_fk')
                ->references('id')
                ->on('assessment_extracurricular_participants')
                ->cascadeOnDelete();
            $table->unique('assessment_extracurricular_participant_id', 'assessment_extracurricular_score_participant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_extracurricular_scores');
        Schema::dropIfExists('assessment_extracurricular_participants');
        Schema::dropIfExists('assessment_extracurricular_teachers');
        Schema::dropIfExists('assessment_extracurriculars');
    }
};
