<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boarding_rapots') || Schema::hasColumn('boarding_rapots', 'kelas_boarding_override')) {
            return;
        }

        Schema::table('boarding_rapots', function (Blueprint $table): void {
            $table->string('kelas_boarding_override', 80)->nullable()->after('predikat_boarding');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('boarding_rapots') || ! Schema::hasColumn('boarding_rapots', 'kelas_boarding_override')) {
            return;
        }

        Schema::table('boarding_rapots', function (Blueprint $table): void {
            $table->dropColumn('kelas_boarding_override');
        });
    }
};
