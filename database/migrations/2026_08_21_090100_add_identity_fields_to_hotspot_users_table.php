<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotspot_users', function (Blueprint $table) {
            if (! Schema::hasColumn('hotspot_users', 'role')) {
                $table->string('role', 20)->default('siswa')->after('username')->comment('siswa|guru');
            }
            if (! Schema::hasColumn('hotspot_users', 'nama')) {
                $table->string('nama')->nullable()->after('role');
            }
            if (! Schema::hasColumn('hotspot_users', 'kelas')) {
                $table->string('kelas')->nullable()->after('nama');
            }
            if (! Schema::hasColumn('hotspot_users', 'input_mode')) {
                $table->string('input_mode', 20)->default('manual')->after('source')->comment('manual|otomatis');
            }
            if (! Schema::hasColumn('hotspot_users', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable()->after('input_mode');
                $table->index('category_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hotspot_users', function (Blueprint $table) {
            foreach (['role', 'nama', 'kelas', 'input_mode'] as $col) {
                if (Schema::hasColumn('hotspot_users', $col)) {
                    $table->dropColumn($col);
                }
            }
            if (Schema::hasColumn('hotspot_users', 'category_id')) {
                $table->dropIndex(['category_id']);
                $table->dropColumn('category_id');
            }
        });
    }
};
