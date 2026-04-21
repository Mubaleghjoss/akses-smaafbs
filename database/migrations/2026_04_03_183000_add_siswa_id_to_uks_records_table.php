<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('uks_records')) {
            return;
        }

        Schema::table('uks_records', function (Blueprint $table): void {
            if (! Schema::hasColumn('uks_records', 'siswa_id')) {
                $table->unsignedInteger('siswa_id')->nullable()->after('id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('uks_records') || ! Schema::hasColumn('uks_records', 'siswa_id')) {
            return;
        }

        Schema::table('uks_records', function (Blueprint $table): void {
            $table->dropColumn('siswa_id');
        });
    }
};
