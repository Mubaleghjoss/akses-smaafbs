<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_materials')) {
            return;
        }

        match (DB::getDriverName()) {
            'mysql' => DB::statement('ALTER TABLE `perpustakaan_literasi_materials` MODIFY `opens_at` DATETIME NULL, MODIFY `closes_at` DATETIME NULL'),
            'pgsql' => DB::statement('ALTER TABLE perpustakaan_literasi_materials ALTER COLUMN opens_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING opens_at::timestamp, ALTER COLUMN closes_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING closes_at::timestamp'),
            default => null,
        };
    }

    public function down(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_materials')) {
            return;
        }

        match (DB::getDriverName()) {
            'mysql' => DB::statement('ALTER TABLE `perpustakaan_literasi_materials` MODIFY `opens_at` DATE NULL, MODIFY `closes_at` DATE NULL'),
            'pgsql' => DB::statement('ALTER TABLE perpustakaan_literasi_materials ALTER COLUMN opens_at TYPE DATE USING opens_at::date, ALTER COLUMN closes_at TYPE DATE USING closes_at::date'),
            default => null,
        };
    }
};
