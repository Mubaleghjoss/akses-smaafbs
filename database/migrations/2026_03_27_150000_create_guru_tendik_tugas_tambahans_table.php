<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('guru_tendik_tugas_tambahans')) {
            return;
        }

        Schema::create('guru_tendik_tugas_tambahans', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('guru_tendik_id')->index();
            $table->string('tugas_tambahan', 150);
            $table->string('no_sk', 100);
            $table->date('tmt');
            $table->date('tst')->nullable();
            $table->string('sk_file_path')->nullable();
            $table->unsignedBigInteger('berkas_guru_id')->nullable()->index();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guru_tendik_tugas_tambahans');
    }
};
