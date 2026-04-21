<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('boarding_keuangan_transaksis');
        Schema::dropIfExists('boarding_keuangan_siswas');
        Schema::dropIfExists('boarding_konseling_mts');
        Schema::dropIfExists('boarding_arsip_mts');
        Schema::dropIfExists('boarding_pencapaians');
        Schema::dropIfExists('boarding_rapots');

        Schema::create('boarding_rapots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('siswa_id');
            $table->string('periode_tahun', 20);
            $table->string('semester', 20);
            $table->date('tanggal_rapot')->nullable();
            $table->string('status_rapot', 30)->default('draft');
            $table->text('ringkasan_pencapaian')->nullable();
            $table->text('catatan_pamong')->nullable();
            $table->text('rekomendasi_tindak_lanjut')->nullable();
            $table->timestamps();

            $table->unique(['siswa_id', 'periode_tahun', 'semester'], 'boarding_rapot_unique');
            $table->index('siswa_id', 'boarding_rapot_siswa_index');
            $table->index(['periode_tahun', 'semester'], 'boarding_rapot_periode_index');
        });

        Schema::create('boarding_pencapaians', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('siswa_id');
            $table->date('tanggal_update_terakhir')->nullable();
            $table->string('status_pencapaian', 30)->default('proses');
            $table->text('surat_quran_tuntas')->nullable();
            $table->text('hadits_tuntas')->nullable();
            $table->text('hafalan_surat')->nullable();
            $table->text('hafalan_doa')->nullable();
            $table->text('hafalan_lainnya')->nullable();
            $table->unsignedInteger('jumlah_surat_dihafal')->default(0);
            $table->unsignedInteger('jumlah_doa_dihafal')->default(0);
            $table->unsignedInteger('jumlah_hadits_dihafal')->default(0);
            $table->text('target_berikutnya')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique('siswa_id', 'boarding_pencapaian_siswa_unique');
            $table->index('siswa_id', 'boarding_pencapaian_siswa_index');
            $table->index('status_pencapaian');
        });

        Schema::create('boarding_arsip_mts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('siswa_id');
            $table->string('angkatan_label', 100)->nullable();
            $table->unsignedSmallInteger('tahun_lulus')->nullable();
            $table->string('status_arsip', 30)->default('arsip');
            $table->string('arsip_ijazah_path')->nullable();
            $table->json('foto_angkatan')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique('siswa_id', 'boarding_arsip_mt_siswa_unique');
            $table->index('siswa_id', 'boarding_arsip_mt_siswa_index');
            $table->index('tahun_lulus');
        });

        Schema::create('boarding_konseling_mts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('siswa_id');
            $table->date('tanggal_konseling');
            $table->string('kategori', 100)->nullable();
            $table->string('prioritas', 30)->default('sedang');
            $table->string('status_tindak_lanjut', 30)->default('terbuka');
            $table->string('konselor', 100)->nullable();
            $table->text('ringkasan_masalah')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->json('lampiran')->nullable();
            $table->timestamps();

            $table->index('siswa_id', 'boarding_konseling_siswa_index');
            $table->index(['tanggal_konseling', 'prioritas'], 'boarding_konseling_tanggal_prioritas_index');
        });

        Schema::create('boarding_keuangan_siswas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('siswa_id');
            $table->string('pamong_nama', 100)->nullable();
            $table->string('angkatan_label', 100)->nullable();
            $table->string('kategori_asrama', 30)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique('siswa_id', 'boarding_keuangan_siswa_unique');
            $table->index('siswa_id', 'boarding_keuangan_siswa_index');
            $table->index('pamong_nama');
        });

        Schema::create('boarding_keuangan_transaksis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boarding_keuangan_siswa_id')
                ->constrained('boarding_keuangan_siswas')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->date('tanggal_transaksi');
            $table->string('jenis_transaksi', 40);
            $table->unsignedInteger('nominal');
            $table->unsignedTinyInteger('periode_bulan')->nullable();
            $table->unsignedSmallInteger('periode_tahun')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['jenis_transaksi', 'tanggal_transaksi'], 'boarding_keuangan_jenis_tanggal_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boarding_keuangan_transaksis');
        Schema::dropIfExists('boarding_keuangan_siswas');
        Schema::dropIfExists('boarding_konseling_mts');
        Schema::dropIfExists('boarding_arsip_mts');
        Schema::dropIfExists('boarding_pencapaians');
        Schema::dropIfExists('boarding_rapots');
    }
};
