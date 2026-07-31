<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_report_generation_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('assessment_period_id');
            $table->foreign('assessment_period_id', 'assessment_report_run_period_fk')
                ->references('id')
                ->on('assessment_periods')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('assessment_report_template_id');
            $table->foreign('assessment_report_template_id', 'assessment_report_run_template_fk')
                ->references('id')
                ->on('assessment_report_templates')
                ->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('status', 30)->default('prepared')->index();
            $table->unsignedInteger('total_students')->default(0);
            $table->unsignedInteger('completed_students')->default(0);
            $table->unsignedInteger('total_classes')->default(0);
            $table->unsignedInteger('completed_classes')->default(0);
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable()->index();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['assessment_period_id', 'assessment_report_template_id', 'revision'],
                'assessment_report_generation_run_unique',
            );
        });

        Schema::table('assessment_report_snapshots', function (Blueprint $table): void {
            $table->unsignedBigInteger('assessment_report_generation_run_id')
                ->nullable()
                ->after('assessment_report_template_id');
            $table->foreign(
                'assessment_report_generation_run_id',
                'assessment_report_snapshot_run_fk',
            )
                ->references('id')
                ->on('assessment_report_generation_runs')
                ->nullOnDelete();
        });

        Schema::table('assessment_class_report_artifacts', function (Blueprint $table): void {
            $table->unsignedBigInteger('assessment_report_generation_run_id')
                ->nullable()
                ->after('assessment_report_template_id');
            $table->foreign(
                'assessment_report_generation_run_id',
                'assessment_class_report_run_fk',
            )
                ->references('id')
                ->on('assessment_report_generation_runs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_class_report_artifacts', function (Blueprint $table): void {
            $table->dropForeign('assessment_class_report_run_fk');
            $table->dropColumn('assessment_report_generation_run_id');
        });

        Schema::table('assessment_report_snapshots', function (Blueprint $table): void {
            $table->dropForeign('assessment_report_snapshot_run_fk');
            $table->dropColumn('assessment_report_generation_run_id');
        });

        Schema::dropIfExists('assessment_report_generation_runs');
    }
};
