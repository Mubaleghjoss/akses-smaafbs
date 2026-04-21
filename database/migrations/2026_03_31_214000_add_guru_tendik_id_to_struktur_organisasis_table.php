<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('struktur_organisasis') || ! Schema::hasTable('guru_tendik')) {
            return;
        }

        $guruTendikIdType = Schema::getColumnType('guru_tendik', 'id');

        Schema::table('struktur_organisasis', function (Blueprint $table): void {
            if (! Schema::hasColumn('struktur_organisasis', 'guru_tendik_id')) {
                if ($guruTendikIdType === 'integer') {
                    $table->integer('guru_tendik_id')->nullable()->after('parent_id');
                } else {
                    $table->unsignedBigInteger('guru_tendik_id')->nullable()->after('parent_id');
                }
            }
        });

        $strukturGuruTendikIdType = Schema::getColumnType('struktur_organisasis', 'guru_tendik_id');

        if ($guruTendikIdType !== $strukturGuruTendikIdType) {
            DB::statement(sprintf(
                'ALTER TABLE `struktur_organisasis` MODIFY `guru_tendik_id` %s NULL',
                $guruTendikIdType === 'integer' ? 'INT' : 'BIGINT UNSIGNED'
            ));
        }

        Schema::table('struktur_organisasis', function (Blueprint $table): void {
            if (! $this->hasIndex('struktur_organisasis', 'struktur_guru_tendik_unique')) {
                $table->unique('guru_tendik_id', 'struktur_guru_tendik_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('struktur_organisasis')) {
            return;
        }

        Schema::table('struktur_organisasis', function (Blueprint $table): void {
            if ($this->hasIndex('struktur_organisasis', 'struktur_guru_tendik_unique')) {
                $table->dropUnique('struktur_guru_tendik_unique');
            }

            if (Schema::hasColumn('struktur_organisasis', 'guru_tendik_id')) {
                $table->dropColumn('guru_tendik_id');
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $definition): bool => ($definition['name'] ?? null) === $index);
    }
};
