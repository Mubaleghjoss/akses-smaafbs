<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('boarding_makna_progresses') && ! Schema::hasColumn('boarding_makna_progresses', 'total_pages')) {
            Schema::table('boarding_makna_progresses', function (Blueprint $table): void {
                $table->unsignedSmallInteger('total_pages')->nullable()->after('remaining_pages');
            });
        }

        if (Schema::hasTable('boarding_hafalan_points')) {
            $now = now();

            DB::table('boarding_hafalan_points')->updateOrInsert(
                [
                    'materi_scope' => 'boarding',
                    'materi_key' => 'materi_quran_bacaan',
                    'jenis' => 'bacaan_quran',
                    'nama_point' => "Bacaan Qur'an",
                ],
                [
                    'urutan' => 1,
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        if (! Schema::hasTable('boarding_materi_progresses')) {
            Schema::create('boarding_materi_progresses', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('boarding_pencapaian_id')
                    ->constrained('boarding_pencapaians')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
                $table->string('target_key', 80);
                $table->string('target_group', 40);
                $table->string('target_name', 120);
                $table->string('grade', 20)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedSmallInteger('urutan')->default(0);
                $table->unsignedBigInteger('updated_by_user_id')->nullable()->index();
                $table->timestamps();

                $table->unique(
                    ['boarding_pencapaian_id', 'target_key'],
                    'boarding_materi_progresses_pencapaian_target_unique'
                );
                $table->index(
                    ['boarding_pencapaian_id', 'target_group', 'urutan'],
                    'boarding_materi_progresses_group_order_index'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('boarding_materi_progresses');

        if (Schema::hasTable('boarding_hafalan_points')) {
            DB::table('boarding_hafalan_points')
                ->where('materi_scope', 'boarding')
                ->where('materi_key', 'materi_quran_bacaan')
                ->where('jenis', 'bacaan_quran')
                ->where('nama_point', "Bacaan Qur'an")
                ->delete();
        }

        if (Schema::hasTable('boarding_makna_progresses') && Schema::hasColumn('boarding_makna_progresses', 'total_pages')) {
            Schema::table('boarding_makna_progresses', function (Blueprint $table): void {
                $table->dropColumn('total_pages');
            });
        }
    }
};
