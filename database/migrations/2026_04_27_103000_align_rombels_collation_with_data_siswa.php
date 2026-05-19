<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasTable('rombels') || ! Schema::hasTable('data_siswa')) {
            return;
        }

        if (! Schema::hasColumn('rombels', 'nama') || ! Schema::hasColumn('data_siswa', 'rombel_saat_ini')) {
            return;
        }

        $studentColumn = $this->columnMetadata('data_siswa', 'rombel_saat_ini');

        $charset = $studentColumn->CHARACTER_SET_NAME ?? null;
        $collation = $studentColumn->COLLATION_NAME ?? null;

        if (! $this->isSafeIdentifier($charset) || ! $this->isSafeIdentifier($collation)) {
            return;
        }

        DB::statement("ALTER TABLE `rombels` MODIFY `nama` VARCHAR(50) CHARACTER SET {$charset} COLLATE {$collation} NOT NULL");

        if (Schema::hasColumn('rombels', 'angkatan')) {
            DB::statement("ALTER TABLE `rombels` MODIFY `angkatan` VARCHAR(20) CHARACTER SET {$charset} COLLATE {$collation} NULL");
        }

        if (Schema::hasColumn('rombels', 'catatan')) {
            DB::statement("ALTER TABLE `rombels` MODIFY `catatan` TEXT CHARACTER SET {$charset} COLLATE {$collation} NULL");
        }
    }

    public function down(): void
    {
        //
    }

    protected function columnMetadata(string $table, string $column): ?object
    {
        return DB::table('information_schema.COLUMNS')
            ->select(['CHARACTER_SET_NAME', 'COLLATION_NAME'])
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->first();
    }

    protected function isSafeIdentifier(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9_]+$/', $value) === 1;
    }
};
