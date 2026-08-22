<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assessment_subject_categories')) {
            Schema::create('assessment_subject_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('name', 120);
                $table->string('type', 20)->index();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('assessment_teaching_assignments')
            && ! Schema::hasColumn('assessment_teaching_assignments', 'assessment_subject_category_id')) {
            Schema::table('assessment_teaching_assignments', function (Blueprint $table): void {
                $table->foreignId('assessment_subject_category_id')
                    ->nullable()
                    ->after('assessment_subject_id')
                    ->constrained('assessment_subject_categories', indexName: 'assessment_teaching_category_fk')
                    ->restrictOnDelete();
            });
        }

        $now = now();
        $categories = [
            ['code' => 'WAJIB', 'name' => 'Mapel Wajib', 'type' => 'wajib', 'sort_order' => 10, 'description' => 'Mata pelajaran wajib pada kelas terkait.', 'is_active' => true],
            ['code' => 'PILIHAN', 'name' => 'Mapel Pilihan', 'type' => 'pilihan', 'sort_order' => 20, 'description' => 'Mata pelajaran pilihan pada kelas terkait.', 'is_active' => true],
            ['code' => 'UMUM-A-LEGACY', 'name' => 'Kelompok A (Umum)', 'type' => 'wajib', 'sort_order' => 900, 'description' => 'Kategori kompatibilitas data lama. Tidak dipakai untuk plotting baru.', 'is_active' => false],
        ];

        foreach ($categories as $category) {
            DB::table('assessment_subject_categories')->updateOrInsert(
                ['code' => $category['code']],
                [...$category, 'updated_at' => $now, 'created_at' => $now],
            );
        }

        if (! Schema::hasTable('assessment_subjects') || ! Schema::hasTable('assessment_teaching_assignments')) {
            return;
        }

        $categoryIds = DB::table('assessment_subject_categories')
            ->whereIn('code', ['WAJIB', 'PILIHAN', 'UMUM-A-LEGACY'])
            ->pluck('id', 'code');

        $wajibSubjectIds = DB::table('assessment_subjects')
            ->where(function ($query): void {
                $query->whereRaw('LOWER(report_group_name) LIKE ?', ['%wajib%'])
                    ->orWhere('report_group_code', 'B');
            })
            ->pluck('id');
        DB::table('assessment_teaching_assignments')
            ->whereNull('assessment_subject_category_id')
            ->whereIn('assessment_subject_id', $wajibSubjectIds)
            ->update(['assessment_subject_category_id' => $categoryIds['WAJIB'] ?? null]);

        $pilihanSubjectIds = DB::table('assessment_subjects')
            ->where(function ($query): void {
                $query->whereRaw('LOWER(report_group_name) LIKE ?', ['%pilih%'])
                    ->orWhere('report_group_code', 'P');
            })
            ->pluck('id');
        DB::table('assessment_teaching_assignments')
            ->whereNull('assessment_subject_category_id')
            ->whereIn('assessment_subject_id', $pilihanSubjectIds)
            ->update(['assessment_subject_category_id' => $categoryIds['PILIHAN'] ?? null]);

        DB::table('assessment_teaching_assignments')
            ->whereNull('assessment_subject_category_id')
            ->update(['assessment_subject_category_id' => $categoryIds['UMUM-A-LEGACY'] ?? null]);
    }

    public function down(): void
    {
        if (Schema::hasTable('assessment_teaching_assignments')
            && Schema::hasColumn('assessment_teaching_assignments', 'assessment_subject_category_id')) {
            Schema::table('assessment_teaching_assignments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('assessment_subject_category_id');
            });
        }

        Schema::dropIfExists('assessment_subject_categories');
    }
};
