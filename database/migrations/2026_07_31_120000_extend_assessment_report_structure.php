<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_subjects', function (Blueprint $table): void {
            $table->string('report_group_code', 40)
                ->default('BELUM')
                ->after('description')
                ->index();
            $table->string('report_group_name', 120)
                ->default('Belum Dikelompokkan')
                ->after('report_group_code');
            $table->unsignedSmallInteger('report_group_sort_order')
                ->default(999)
                ->after('report_group_name');
        });

        Schema::table('assessment_period_assignments', function (Blueprint $table): void {
            $table->string('subject_group_code_snapshot', 40)
                ->default('BELUM')
                ->after('subject_name_snapshot');
            $table->string('subject_group_name_snapshot', 120)
                ->default('Belum Dikelompokkan')
                ->after('subject_group_code_snapshot');
            $table->unsignedSmallInteger('subject_group_sort_order_snapshot')
                ->default(999)
                ->after('subject_group_name_snapshot');
            $table->unsignedSmallInteger('subject_sort_order_snapshot')
                ->default(0)
                ->after('subject_group_sort_order_snapshot');
        });

        Schema::table('assessment_homeroom_reports', function (Blueprint $table): void {
            $table->string('spiritual_predicate', 30)->nullable()->after('absent_days');
            $table->text('spiritual_description')->nullable()->after('spiritual_predicate');
            $table->string('social_predicate', 30)->nullable()->after('spiritual_description');
            $table->text('social_description')->nullable()->after('social_predicate');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_homeroom_reports', function (Blueprint $table): void {
            $table->dropColumn([
                'spiritual_predicate',
                'spiritual_description',
                'social_predicate',
                'social_description',
            ]);
        });

        Schema::table('assessment_period_assignments', function (Blueprint $table): void {
            $table->dropColumn([
                'subject_group_code_snapshot',
                'subject_group_name_snapshot',
                'subject_group_sort_order_snapshot',
                'subject_sort_order_snapshot',
            ]);
        });

        Schema::table('assessment_subjects', function (Blueprint $table): void {
            $table->dropIndex(['report_group_code']);
            $table->dropColumn([
                'report_group_code',
                'report_group_name',
                'report_group_sort_order',
            ]);
        });
    }
};
