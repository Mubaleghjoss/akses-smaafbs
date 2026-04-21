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
        Schema::table('prokers', function (Blueprint $table) {
            $table->string('point_dari', 150)->nullable()->after('periode_label');
            $table->unsignedInteger('nomor_urut')->nullable()->after('point_dari');
            $table->json('jadwal_bulanan')->nullable()->after('target_selesai');
            $table->text('jadwal_ringkas')->nullable()->after('jadwal_bulanan');
            $table->text('waktu_pelaksanaan')->nullable()->after('jadwal_ringkas');
            $table->string('rab_global')->nullable()->after('waktu_pelaksanaan');
            $table->text('keterangan')->nullable()->after('rab_global');
            $table->index('point_dari');
            $table->index('nomor_urut');
            $table->index('periode_tahun');
            $table->index('status');
            $table->index('target_selesai');
            $table->index(['bidang_id', 'periode_tahun']);
        });

        Schema::table('proker_indikators', function (Blueprint $table) {
            $table->index(['proker_id', 'is_checked']);
            $table->index(['proker_id', 'urutan']);
        });

        Schema::table('proker_updates', function (Blueprint $table) {
            $table->index(['proker_id', 'tanggal_update']);
            $table->index('tanggal_update');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proker_updates', function (Blueprint $table) {
            $table->dropIndex(['proker_id', 'tanggal_update']);
            $table->dropIndex(['tanggal_update']);
        });

        Schema::table('proker_indikators', function (Blueprint $table) {
            $table->dropIndex(['proker_id', 'is_checked']);
            $table->dropIndex(['proker_id', 'urutan']);
        });

        Schema::table('prokers', function (Blueprint $table) {
            $table->dropIndex(['point_dari']);
            $table->dropIndex(['nomor_urut']);
            $table->dropIndex(['periode_tahun']);
            $table->dropIndex(['status']);
            $table->dropIndex(['target_selesai']);
            $table->dropIndex(['bidang_id', 'periode_tahun']);
            $table->dropColumn([
                'point_dari',
                'nomor_urut',
                'jadwal_bulanan',
                'jadwal_ringkas',
                'waktu_pelaksanaan',
                'rab_global',
                'keterangan',
            ]);
        });
    }
};
