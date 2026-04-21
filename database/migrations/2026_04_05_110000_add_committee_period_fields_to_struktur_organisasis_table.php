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

        $hasPeriodYearColumn = Schema::hasColumn('struktur_organisasis', 'periode_tahun');
        $hasPeriodLabelColumn = Schema::hasColumn('struktur_organisasis', 'periode_label');

        if (! $hasPeriodYearColumn || ! $hasPeriodLabelColumn) {
            Schema::table('struktur_organisasis', function (Blueprint $table) use ($hasPeriodYearColumn, $hasPeriodLabelColumn): void {
                if (! $hasPeriodYearColumn) {
                    $table->unsignedSmallInteger('periode_tahun')->nullable()->after('kategori');
                }

                if (! $hasPeriodLabelColumn) {
                    $table->string('periode_label', 100)->nullable()->after('periode_tahun');
                }
            });
        }

        $currentYear = (int) now()->format('Y');

        DB::table('struktur_organisasis')
            ->where('kategori', StrukturOrganisasi::CATEGORY_COMMITTEE)
            ->whereNull('periode_tahun')
            ->update([
                'periode_tahun' => $currentYear,
                'periode_label' => (string) $currentYear,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('struktur_organisasis')) {
            return;
        }

        $hasPeriodYearColumn = Schema::hasColumn('struktur_organisasis', 'periode_tahun');
        $hasPeriodLabelColumn = Schema::hasColumn('struktur_organisasis', 'periode_label');

        if (! $hasPeriodYearColumn && ! $hasPeriodLabelColumn) {
            return;
        }

        Schema::table('struktur_organisasis', function (Blueprint $table) use ($hasPeriodYearColumn, $hasPeriodLabelColumn): void {
            if ($hasPeriodLabelColumn) {
                $table->dropColumn('periode_label');
            }

            if ($hasPeriodYearColumn) {
                $table->dropColumn('periode_tahun');
            }
        });
    }
};
