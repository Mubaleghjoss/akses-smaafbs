<?php

use App\Enums\Assessment\AssignmentStatus;
use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\ReportGenerationStatus;
use App\Enums\Assessment\ScoreSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_academic_years', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('assessment_semesters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_academic_year_id')
                ->constrained('assessment_academic_years')
                ->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(
                ['assessment_academic_year_id', 'code'],
                'assessment_semesters_year_code_unique',
            );
        });

        Schema::create('assessment_subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('assessment_teaching_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_semester_id')
                ->constrained('assessment_semesters')
                ->restrictOnDelete();
            $table->foreignId('assessment_subject_id')
                ->constrained('assessment_subjects')
                ->restrictOnDelete();
            $table->bigInteger('teacher_id')->index();
            $table->unsignedBigInteger('rombel_id')->index();
            $table->string('teacher_name_snapshot', 150);
            $table->string('subject_name_snapshot', 150);
            $table->string('rombel_name_snapshot', 100);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(
                ['assessment_semester_id', 'assessment_subject_id', 'teacher_id', 'rombel_id'],
                'assessment_teaching_scope_unique',
            );
        });

        Schema::create('assessment_homeroom_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_semester_id')
                ->constrained('assessment_semesters')
                ->restrictOnDelete();
            $table->bigInteger('teacher_id')->index();
            $table->unsignedBigInteger('rombel_id')->index();
            $table->string('teacher_name_snapshot', 150);
            $table->string('rombel_name_snapshot', 100);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(
                ['assessment_semester_id', 'rombel_id'],
                'assessment_homeroom_semester_rombel_unique',
            );
        });

        Schema::create('assessment_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_academic_year_id')
                ->constrained('assessment_academic_years')
                ->restrictOnDelete();
            $table->foreignId('assessment_semester_id')
                ->constrained('assessment_semesters')
                ->restrictOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('type', 20)->default(AssessmentType::ASTS->value)->index();
            $table->string('status', 30)->default(AssessmentPeriodStatus::DRAFT->value)->index();
            $table->dateTime('entry_start_at')->nullable();
            $table->dateTime('entry_end_at')->nullable();
            $table->date('report_date')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['assessment_academic_year_id', 'assessment_semester_id', 'type'],
                'assessment_period_year_semester_type_unique',
            );
        });

        Schema::create('assessment_period_rombels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_period_id')
                ->constrained('assessment_periods')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('source_rombel_id')->index();
            $table->string('rombel_name_snapshot', 100);
            $table->string('grade_level', 20)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(
                ['assessment_period_id', 'source_rombel_id'],
                'assessment_period_rombel_source_unique',
            );
        });

        Schema::create('assessment_period_students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_period_id')
                ->constrained('assessment_periods')
                ->cascadeOnDelete();
            $table->foreignId('assessment_period_rombel_id')
                ->constrained('assessment_period_rombels')
                ->restrictOnDelete();
            $table->bigInteger('student_id')->index();
            $table->string('nis_snapshot', 50)->nullable();
            $table->string('nisn_snapshot', 50)->nullable();
            $table->string('student_name_snapshot', 150);
            $table->string('gender_snapshot', 10)->nullable();
            $table->string('rombel_name_snapshot', 100);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(
                ['assessment_period_id', 'student_id'],
                'assessment_period_student_unique',
            );
        });

        Schema::create('assessment_period_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_period_id')
                ->constrained('assessment_periods')
                ->cascadeOnDelete();
            $table->foreignId('source_teaching_assignment_id')
                ->nullable()
                ->constrained('assessment_teaching_assignments', indexName: 'assessment_pa_teaching_fk')
                ->nullOnDelete();
            $table->foreignId('assessment_period_rombel_id')
                ->constrained('assessment_period_rombels', indexName: 'assessment_pa_rombel_fk')
                ->restrictOnDelete();
            $table->bigInteger('teacher_id')->index();
            $table->foreignId('assessment_subject_id')
                ->constrained('assessment_subjects')
                ->restrictOnDelete();
            $table->string('teacher_name_snapshot', 150);
            $table->string('subject_name_snapshot', 150);
            $table->string('rombel_name_snapshot', 100);
            $table->string('status', 30)->default(AssignmentStatus::DRAFT->value)->index();
            $table->unsignedInteger('lock_version')->default(1);
            $table->dateTime('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable()->index();
            $table->dateTime('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable()->index();
            $table->dateTime('returned_at')->nullable();
            $table->unsignedBigInteger('returned_by')->nullable()->index();
            $table->text('returned_reason')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['assessment_period_id', 'teacher_id', 'assessment_subject_id', 'assessment_period_rombel_id'],
                'assessment_period_assignment_scope_unique',
            );
        });

        Schema::create('assessment_period_homerooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_period_id')
                ->constrained('assessment_periods')
                ->cascadeOnDelete();
            $table->foreignId('source_homeroom_assignment_id')
                ->nullable()
                ->constrained('assessment_homeroom_assignments', indexName: 'assessment_ph_homeroom_fk')
                ->nullOnDelete();
            $table->foreignId('assessment_period_rombel_id')
                ->constrained('assessment_period_rombels')
                ->restrictOnDelete();
            $table->bigInteger('teacher_id')->index();
            $table->string('teacher_name_snapshot', 150);
            $table->string('rombel_name_snapshot', 100);
            $table->timestamps();

            $table->unique(
                ['assessment_period_id', 'assessment_period_rombel_id'],
                'assessment_period_homeroom_rombel_unique',
            );
        });

        Schema::create('assessment_schemes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_period_id')
                ->constrained('assessment_periods')
                ->cascadeOnDelete();
            $table->foreignId('assessment_subject_id')
                ->nullable()
                ->constrained('assessment_subjects')
                ->restrictOnDelete();
            // Logical reference to the legacy rombels master. It intentionally
            // has no foreign key so historical assessment configuration does
            // not cascade when a legacy class is archived or removed.
            $table->unsignedBigInteger('source_rombel_id')
                ->nullable()
                ->index('assessment_schemes_source_rombel_idx');
            $table->foreignId('assessment_period_rombel_id')
                ->nullable()
                ->constrained('assessment_period_rombels')
                ->restrictOnDelete();
            $table->string('name', 150);
            $table->unsignedTinyInteger('rounding_precision')->default(2);
            $table->decimal('minimum_score', 10, 4)->default(0);
            $table->decimal('maximum_score', 10, 4)->default(100);
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(
                [
                    'assessment_period_id',
                    'assessment_subject_id',
                    'source_rombel_id',
                    'assessment_period_rombel_id',
                    'name',
                ],
                'assessment_scheme_scope_name_unique',
            );
        });

        Schema::create('assessment_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_scheme_id')
                ->constrained('assessment_schemes')
                ->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->string('domain', 100)->nullable();
            $table->decimal('weight', 7, 4);
            $table->decimal('maximum_score', 10, 4)->default(100);
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('score_source', 30)->default(ScoreSource::MANUAL->value)->index();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(
                ['assessment_scheme_id', 'code'],
                'assessment_component_scheme_code_unique',
            );
        });

        Schema::create('assessment_student_subject_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_period_id')
                ->constrained('assessment_periods')
                ->cascadeOnDelete();
            $table->foreignId('assessment_period_student_id')
                ->constrained('assessment_period_students', indexName: 'assessment_result_student_fk')
                ->cascadeOnDelete();
            $table->foreignId('assessment_period_assignment_id')
                ->constrained('assessment_period_assignments', indexName: 'assessment_result_assignment_fk')
                ->cascadeOnDelete();
            $table->decimal('final_score', 10, 4)->nullable();
            $table->string('predicate', 20)->nullable();
            $table->text('description')->nullable();
            $table->json('calculation_detail')->nullable();
            $table->string('formula_version', 40)->default('v1');
            $table->dateTime('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['assessment_period_id', 'assessment_period_student_id', 'assessment_period_assignment_id'],
                'assessment_student_subject_result_unique',
            );
        });

        Schema::create('assessment_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_period_assignment_id')
                ->constrained('assessment_period_assignments')
                ->cascadeOnDelete();
            $table->foreignId('assessment_period_student_id')
                ->constrained('assessment_period_students')
                ->cascadeOnDelete();
            $table->foreignId('assessment_component_id')
                ->constrained('assessment_components')
                ->restrictOnDelete();
            $table->decimal('score', 10, 4)->nullable();
            $table->text('notes')->nullable();
            $table->string('source', 30)->default(ScoreSource::MANUAL->value)->index();
            $table->foreignId('source_result_id')
                ->nullable()
                ->constrained('assessment_student_subject_results')
                ->nullOnDelete();
            $table->decimal('source_score_snapshot', 10, 4)->nullable();
            $table->unsignedBigInteger('entered_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['assessment_period_assignment_id', 'assessment_period_student_id', 'assessment_component_id'],
                'assessment_score_assignment_student_component_unique',
            );
        });

        Schema::create('assessment_homeroom_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_period_id')
                ->constrained('assessment_periods')
                ->cascadeOnDelete();
            $table->foreignId('assessment_period_student_id')
                ->constrained('assessment_period_students')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('sick_days')->default(0);
            $table->unsignedSmallInteger('permission_days')->default(0);
            $table->unsignedSmallInteger('absent_days')->default(0);
            $table->json('extracurricular_data')->nullable();
            $table->json('achievement_data')->nullable();
            $table->text('homeroom_note')->nullable();
            $table->string('promotion_status', 50)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['assessment_period_id', 'assessment_period_student_id'],
                'assessment_homeroom_report_student_unique',
            );
        });

        Schema::create('assessment_report_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50);
            $table->string('type', 20)->index();
            $table->string('name', 150);
            $table->unsignedInteger('version')->default(1);
            $table->string('view_path', 200);
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->date('effective_from')->nullable();
            $table->timestamps();

            $table->unique(
                ['code', 'version'],
                'assessment_report_template_code_version_unique',
            );
        });

        Schema::create('assessment_report_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_period_id')
                ->constrained('assessment_periods')
                ->restrictOnDelete();
            $table->foreignId('assessment_period_student_id')
                ->constrained('assessment_period_students')
                ->restrictOnDelete();
            $table->foreignId('assessment_report_template_id')
                ->constrained('assessment_report_templates', indexName: 'assessment_snapshot_template_fk')
                ->restrictOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('template_version');
            $table->json('snapshot_data');
            $table->string('generation_status', 30)->default(ReportGenerationStatus::PENDING->value)->index();
            $table->string('pdf_path', 500)->nullable();
            $table->string('checksum', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('generated_at')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['assessment_period_id', 'assessment_period_student_id', 'assessment_report_template_id', 'revision'],
                'assessment_report_snapshot_revision_unique',
            );
        });

        Schema::create('assessment_class_report_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_period_id')
                ->constrained('assessment_periods')
                ->restrictOnDelete();
            $table->foreignId('assessment_period_rombel_id')
                ->constrained('assessment_period_rombels', indexName: 'assessment_artifact_rombel_fk')
                ->restrictOnDelete();
            $table->foreignId('assessment_report_template_id')
                ->constrained('assessment_report_templates', indexName: 'assessment_artifact_template_fk')
                ->restrictOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->string('generation_status', 30)->default(ReportGenerationStatus::PENDING->value)->index();
            $table->string('pdf_path', 500)->nullable();
            $table->string('checksum', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('queued_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('generated_at')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['assessment_period_id', 'assessment_period_rombel_id', 'assessment_report_template_id', 'revision'],
                'assessment_class_report_artifact_revision_unique',
            );
        });

        Schema::create('assessment_report_share_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_report_snapshot_id')
                ->constrained('assessment_report_snapshots', indexName: 'assessment_share_snapshot_fk')
                ->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at')->index();
            $table->dateTime('revoked_at')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->dateTime('last_accessed_at')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();
        });

        Schema::create('assessment_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_period_id')
                ->nullable()
                ->constrained('assessment_periods')
                ->nullOnDelete();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('event', 80)->index();
            $table->string('subject_type', 180);
            $table->unsignedBigInteger('subject_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['subject_type', 'subject_id'],
                'assessment_audit_subject_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_audit_logs');
        Schema::dropIfExists('assessment_report_share_links');
        Schema::dropIfExists('assessment_class_report_artifacts');
        Schema::dropIfExists('assessment_report_snapshots');
        Schema::dropIfExists('assessment_report_templates');
        Schema::dropIfExists('assessment_homeroom_reports');
        Schema::dropIfExists('assessment_scores');
        Schema::dropIfExists('assessment_student_subject_results');
        Schema::dropIfExists('assessment_components');
        Schema::dropIfExists('assessment_schemes');
        Schema::dropIfExists('assessment_period_homerooms');
        Schema::dropIfExists('assessment_period_assignments');
        Schema::dropIfExists('assessment_period_students');
        Schema::dropIfExists('assessment_period_rombels');
        Schema::dropIfExists('assessment_periods');
        Schema::dropIfExists('assessment_homeroom_assignments');
        Schema::dropIfExists('assessment_teaching_assignments');
        Schema::dropIfExists('assessment_subjects');
        Schema::dropIfExists('assessment_semesters');
        Schema::dropIfExists('assessment_academic_years');
    }
};
