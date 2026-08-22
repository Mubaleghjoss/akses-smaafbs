<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boarding_bacaan_assessments') || Schema::hasColumn('boarding_bacaan_assessments', 'kelas_bacaan')) {
            return;
        }

        Schema::table('boarding_bacaan_assessments', function (Blueprint $table): void {
            $table->string('kelas_bacaan', 10)->nullable()->after('assessed_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('boarding_bacaan_assessments') || ! Schema::hasColumn('boarding_bacaan_assessments', 'kelas_bacaan')) {
            return;
        }

        Schema::table('boarding_bacaan_assessments', function (Blueprint $table): void {
            $table->dropColumn('kelas_bacaan');
        });
    }
};
