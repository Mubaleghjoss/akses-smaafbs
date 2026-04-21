<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boarding_makna_progresses')) {
            Schema::create('boarding_makna_progresses', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('boarding_pencapaian_id')
                    ->constrained('boarding_pencapaians')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
                $table->string('target_key', 100);
                $table->string('target_group', 40);
                $table->string('target_name', 191);
                $table->unsignedSmallInteger('urutan')->default(0);
                $table->string('status', 20)->default('belum_diisi');
                $table->unsignedSmallInteger('remaining_pages')->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable()->index();
                $table->timestamps();

                $table->unique(
                    ['boarding_pencapaian_id', 'target_key'],
                    'boarding_makna_progresses_pencapaian_target_unique'
                );
                $table->index(
                    ['boarding_pencapaian_id', 'target_group', 'urutan'],
                    'boarding_makna_progresses_group_order_index'
                );
                $table->index('status', 'boarding_makna_progresses_status_index');
            });
        }

        if (! Schema::hasTable('boarding_bacaan_assessments')) {
            Schema::create('boarding_bacaan_assessments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('boarding_pencapaian_id')
                    ->constrained('boarding_pencapaians')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
                $table->date('assessed_at');
                $table->string('pp_grade', 1);
                $table->string('kl_grade', 1);
                $table->string('tj_grade', 1);
                $table->string('mj_grade', 1);
                $table->unsignedBigInteger('reviewer_user_id')->nullable()->index();
                $table->string('reviewer_name', 100)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(
                    ['boarding_pencapaian_id', 'assessed_at', 'id'],
                    'boarding_bacaan_assessments_pencapaian_date_index'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('boarding_bacaan_assessments');
        Schema::dropIfExists('boarding_makna_progresses');
    }
};
