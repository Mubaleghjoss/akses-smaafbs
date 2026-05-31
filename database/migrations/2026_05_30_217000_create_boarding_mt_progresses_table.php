<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('boarding_mt_progresses')) {
            return;
        }

        Schema::create('boarding_mt_progresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('boarding_pencapaian_id')
                ->constrained('boarding_pencapaians')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('target_key', 120);
            $table->string('target_group', 50);
            $table->string('target_name', 191);
            $table->string('input_type', 20);
            $table->string('grade_scale', 30)->nullable();
            $table->unsignedSmallInteger('progress_value')->nullable();
            $table->unsignedSmallInteger('target_total')->nullable();
            $table->string('unit_label', 40)->nullable();
            $table->string('grade', 20)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->unsignedBigInteger('updated_by_user_id')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['boarding_pencapaian_id', 'target_key'],
                'boarding_mt_progresses_pencapaian_target_unique'
            );
            $table->index(
                ['boarding_pencapaian_id', 'target_group', 'urutan'],
                'boarding_mt_progresses_group_order_index'
            );
            $table->index('grade', 'boarding_mt_progresses_grade_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boarding_mt_progresses');
    }
};
