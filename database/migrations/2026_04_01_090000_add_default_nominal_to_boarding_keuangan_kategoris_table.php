<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boarding_keuangan_kategoris') || Schema::hasColumn('boarding_keuangan_kategoris', 'default_nominal')) {
            return;
        }

        Schema::table('boarding_keuangan_kategoris', function (Blueprint $table): void {
            $table->unsignedInteger('default_nominal')
                ->nullable()
                ->after('is_system');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('boarding_keuangan_kategoris') || ! Schema::hasColumn('boarding_keuangan_kategoris', 'default_nominal')) {
            return;
        }

        Schema::table('boarding_keuangan_kategoris', function (Blueprint $table): void {
            $table->dropColumn('default_nominal');
        });
    }
};
