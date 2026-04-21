<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('proker_bidangs', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('kode', 50)->nullable()->unique();
            $table->string('penanggung_jawab')->nullable();
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('prokers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bidang_id')->constrained('proker_bidangs')->cascadeOnDelete();
            $table->string('nama');
            $table->unsignedSmallInteger('periode_tahun');
            $table->string('periode_label', 100)->nullable();
            $table->string('penanggung_jawab')->nullable();
            $table->date('target_mulai')->nullable();
            $table->date('target_selesai')->nullable();
            $table->enum('status', ['draft', 'berjalan', 'terkendala', 'selesai'])->default('draft');
            $table->enum('prioritas', ['rendah', 'sedang', 'tinggi'])->default('sedang');
            $table->unsignedTinyInteger('progress_persen')->default(0);
            $table->text('deskripsi')->nullable();
            $table->text('output_target')->nullable();
            $table->text('evaluasi_akhir')->nullable();
            $table->text('tindak_lanjut_umum')->nullable();
            $table->dateTime('last_monitored_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('proker_indikators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proker_id')->constrained('prokers')->cascadeOnDelete();
            $table->unsignedInteger('urutan')->default(0);
            $table->string('indikator');
            $table->text('target')->nullable();
            $table->unsignedTinyInteger('bobot')->default(1);
            $table->boolean('is_checked')->default(false);
            $table->dateTime('checked_at')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('proker_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proker_id')->constrained('prokers')->cascadeOnDelete();
            $table->date('tanggal_update');
            $table->enum('status_snapshot', ['draft', 'berjalan', 'terkendala', 'selesai'])->default('draft');
            $table->unsignedTinyInteger('progress_persen')->nullable();
            $table->text('ringkasan')->nullable();
            $table->text('evaluasi')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->json('dokumentasi')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proker_updates');
        Schema::dropIfExists('proker_indikators');
        Schema::dropIfExists('prokers');
        Schema::dropIfExists('proker_bidangs');
    }
};
