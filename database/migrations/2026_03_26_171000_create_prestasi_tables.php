<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prestasis')) {
            Schema::create('prestasis', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('siswa_id')->index();
                $table->string('nama_lomba', 150);
                $table->string('kategori', 30)->nullable()->index();
                $table->date('tanggal_prestasi')->nullable()->index();
                $table->string('penyelenggara', 150)->nullable();
                $table->string('juara', 100)->nullable();
                $table->string('hadiah', 255)->nullable();
                $table->text('keterangan')->nullable();
                $table->json('dokumentasi')->nullable();
                $table->json('sertifikat_files')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('prestasi_histories')) {
            Schema::create('prestasi_histories', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('prestasi_id')->index();
                $table->string('aksi', 40);
                $table->string('judul_ringkas', 255)->nullable();
                $table->json('snapshot')->nullable();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name', 100)->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prestasi_histories');
        Schema::dropIfExists('prestasis');
    }
};
