<?php

use App\Models\StrukturOrganisasi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('struktur_organisasis')) {
            return;
        }

        Schema::table('struktur_organisasis', function (Blueprint $table): void {
            if (! Schema::hasColumn('struktur_organisasis', 'kategori')) {
                $table->string('kategori', 20)
                    ->default(StrukturOrganisasi::CATEGORY_SCHOOL)
                    ->after('nama');
                $table->index('kategori', 'struktur_kategori_index');
            }
        });

        $rows = DB::table('struktur_organisasis')
            ->select(['id', 'parent_id', 'jabatan', 'nama'])
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $keywordIds = $rows
            ->filter(function ($row): bool {
                $jabatan = mb_strtolower(trim((string) ($row->jabatan ?? '')));
                $nama = mb_strtolower(trim((string) ($row->nama ?? '')));

                return str_contains($jabatan, 'komite') || str_contains($nama, 'komite');
            })
            ->pluck('id')
            ->all();

        if ($keywordIds === []) {
            return;
        }

        $childrenByParent = $rows
            ->groupBy(fn ($row): string => (string) ($row->parent_id ?? 'root'));

        $komiteIds = [];
        $pending = $keywordIds;

        while ($pending !== []) {
            $currentId = array_shift($pending);

            if ($currentId === null || in_array($currentId, $komiteIds, true)) {
                continue;
            }

            $komiteIds[] = $currentId;

            foreach ($childrenByParent->get((string) $currentId, collect()) as $child) {
                $pending[] = $child->id;
            }
        }

        DB::table('struktur_organisasis')
            ->whereIn('id', $komiteIds)
            ->update(['kategori' => StrukturOrganisasi::CATEGORY_COMMITTEE]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('struktur_organisasis') || ! Schema::hasColumn('struktur_organisasis', 'kategori')) {
            return;
        }

        Schema::table('struktur_organisasis', function (Blueprint $table): void {
            if ($this->hasIndex('struktur_organisasis', 'struktur_kategori_index')) {
                $table->dropIndex('struktur_kategori_index');
            }

            $table->dropColumn('kategori');
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $definition): bool => ($definition['name'] ?? null) === $index);
    }
};
