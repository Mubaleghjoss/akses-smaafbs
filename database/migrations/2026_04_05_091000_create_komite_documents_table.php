<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('komite_documents')) {
            return;
        }

        Schema::create('komite_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('arsip_tahun')->index();
            $table->string('jenis_dokumen', 40)->index();
            $table->string('judul', 180);
            $table->string('nomor_dokumen', 120)->nullable();
            $table->date('tanggal_dokumen')->nullable();
            $table->string('file_path')->nullable();
            $table->json('dokumentasi')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['arsip_tahun', 'jenis_dokumen'], 'komite_documents_year_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('komite_documents');
    }
};
