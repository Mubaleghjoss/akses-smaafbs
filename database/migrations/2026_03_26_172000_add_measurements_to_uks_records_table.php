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
            if (! Schema::hasColumn('uks_records', 'berat_badan')) {
                $table->decimal('berat_badan', 6, 2)->nullable()->after('penanganan');
            }

            if (! Schema::hasColumn('uks_records', 'tinggi_badan')) {
                $table->decimal('tinggi_badan', 6, 2)->nullable()->after('berat_badan');
            }

            if (! Schema::hasColumn('uks_records', 'lingkar_kepala')) {
                $table->decimal('lingkar_kepala', 6, 2)->nullable()->after('tinggi_badan');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('uks_records')) {
            return;
        }

        Schema::table('uks_records', function (Blueprint $table): void {
            foreach (['berat_badan', 'tinggi_badan', 'lingkar_kepala'] as $column) {
                if (Schema::hasColumn('uks_records', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
