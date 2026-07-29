<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_dispensations')) {
            return;
        }

        Schema::create('perpustakaan_literasi_dispensations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('material_id')->index();
            $table->unsignedBigInteger('data_siswa_id')->index();
            $table->string('reason', 20)->index();
            $table->string('student_name_snapshot', 180);
            $table->string('student_class_snapshot', 100)->nullable()->index();
            $table->unsignedBigInteger('confirmed_by')->nullable()->index();
            $table->timestamp('confirmed_at')->index();
            $table->string('note', 1000)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(
                ['material_id', 'data_siswa_id'],
                'perpus_lit_disp_material_student_unique',
            );
            $table->index(
                ['material_id', 'confirmed_at'],
                'perpus_lit_disp_material_confirmed_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perpustakaan_literasi_dispensations');
    }
};
