<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('catatan_bks')) {
            return;
        }

        Schema::create('catatan_bks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('siswa_id');
            $table->date('tanggal_konseling');
            $table->string('topik_pembahasan', 180);
            $table->text('hasil_konseling');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('siswa_id', 'catatan_bks_siswa_index');
            $table->index('tanggal_konseling', 'catatan_bks_tanggal_index');
            $table->index('created_by', 'catatan_bks_created_by_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catatan_bks');
    }
};
