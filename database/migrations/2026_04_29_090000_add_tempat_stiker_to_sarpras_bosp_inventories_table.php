<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sarpras_bosp_inventories')) {
            return;
        }

        Schema::table('sarpras_bosp_inventories', function (Blueprint $table): void {
            if (! Schema::hasColumn('sarpras_bosp_inventories', 'tempat_stiker')) {
                $table->string('tempat_stiker', 180)->nullable()->after('lokasi_barang');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sarpras_bosp_inventories')) {
            return;
        }

        Schema::table('sarpras_bosp_inventories', function (Blueprint $table): void {
            if (Schema::hasColumn('sarpras_bosp_inventories', 'tempat_stiker')) {
                $table->dropColumn('tempat_stiker');
            }
        });
    }
};
