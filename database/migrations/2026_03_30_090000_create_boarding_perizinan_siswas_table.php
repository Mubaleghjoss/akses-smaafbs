<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('boarding_perizinan_siswas')) {
            return;
        }

        Schema::create('boarding_perizinan_siswas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('siswa_id');
            $table->string('judul_izin', 150);
            $table->date('tanggal_izin');
            $table->time('waktu_izin')->nullable();
            $table->text('detail_izin')->nullable();
            $table->date('tanggal_kembali')->nullable();
            $table->time('waktu_kembali')->nullable();
            $table->text('detail_kembali')->nullable();
            $table->string('kafaroh_keterlambatan', 150)->nullable();
            $table->string('status_perizinan', 20)->default('pending');
            $table->unsignedBigInteger('dibuat_oleh')->nullable();
            $table->unsignedBigInteger('diproses_oleh')->nullable();
            $table->timestamps();

            $table->index('siswa_id', 'boarding_perizinan_siswa_index');
            $table->index('judul_izin', 'boarding_perizinan_judul_index');
            $table->index('status_perizinan', 'boarding_perizinan_status_index');
            $table->index('dibuat_oleh', 'boarding_perizinan_dibuat_oleh_index');
            $table->index('diproses_oleh', 'boarding_perizinan_diproses_oleh_index');
            $table->index(['siswa_id', 'tanggal_izin'], 'boarding_perizinan_siswa_tanggal_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boarding_perizinan_siswas');
    }
};
