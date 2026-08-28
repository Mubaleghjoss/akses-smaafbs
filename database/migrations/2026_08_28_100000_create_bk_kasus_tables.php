<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bk_kasus')) {
            Schema::create('bk_kasus', function (Blueprint $table): void {
                $table->id();
                $table->date('tanggal_kasus');
                $table->string('judul_kasus', 180);
                $table->text('keterangan_kasus');
                $table->string('kategori', 60)->nullable();
                $table->string('tingkat', 20)->nullable();
                $table->text('tindak_lanjut')->nullable();
                $table->string('status_tindak_lanjut', 20)->default('belum');
                $table->date('tanggal_tindak_lanjut')->nullable();
                $table->string('pelapor', 120)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('tanggal_kasus', 'bk_kasus_tanggal_index');
                $table->index('kategori', 'bk_kasus_kategori_index');
                $table->index('status_tindak_lanjut', 'bk_kasus_status_index');
                $table->index('created_by', 'bk_kasus_created_by_index');
            });
        }

        if (! Schema::hasTable('bk_kasus_siswa')) {
            Schema::create('bk_kasus_siswa', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('bk_kasus_id');
                $table->unsignedBigInteger('siswa_id');
                $table->string('rombel_snapshot', 50)->nullable();
                $table->timestamps();

                $table->unique(['bk_kasus_id', 'siswa_id'], 'bk_kasus_siswa_unique');
                $table->index('siswa_id', 'bk_kasus_siswa_siswa_index');
                $table->index('rombel_snapshot', 'bk_kasus_siswa_rombel_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bk_kasus_siswa');
        Schema::dropIfExists('bk_kasus');
    }
};
