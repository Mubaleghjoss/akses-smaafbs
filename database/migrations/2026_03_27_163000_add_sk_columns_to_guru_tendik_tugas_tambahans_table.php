<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guru_tendik_tugas_tambahans')) {
            return;
        }

        Schema::table('guru_tendik_tugas_tambahans', function (Blueprint $table): void {
            if (! Schema::hasColumn('guru_tendik_tugas_tambahans', 'sk_file_path')) {
                $table->string('sk_file_path')->nullable()->after('tst');
            }

            if (! Schema::hasColumn('guru_tendik_tugas_tambahans', 'berkas_guru_id')) {
                $table->unsignedBigInteger('berkas_guru_id')->nullable()->after('sk_file_path')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('guru_tendik_tugas_tambahans')) {
            return;
        }

        Schema::table('guru_tendik_tugas_tambahans', function (Blueprint $table): void {
            foreach (['berkas_guru_id', 'sk_file_path'] as $column) {
                if (Schema::hasColumn('guru_tendik_tugas_tambahans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
