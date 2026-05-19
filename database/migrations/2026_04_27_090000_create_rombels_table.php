<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $studentRombelCollation = $this->studentRombelCollation();

        if (! Schema::hasTable('rombels')) {
            Schema::create('rombels', function (Blueprint $table) use ($studentRombelCollation): void {
                $table->id();
                $nama = $table->string('nama', 50)->unique();
                $angkatan = $table->string('angkatan', 20)->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $catatan = $table->text('catatan')->nullable();
                $table->timestamps();

                if ($studentRombelCollation !== null) {
                    $nama->collation($studentRombelCollation);
                    $angkatan->collation($studentRombelCollation);
                    $catatan->collation($studentRombelCollation);
                }
            });
        }

        $this->seedFromDataSiswa();
    }

    public function down(): void
    {
        Schema::dropIfExists('rombels');
    }

    protected function seedFromDataSiswa(): void
    {
        if (! Schema::hasTable('data_siswa') || ! Schema::hasColumn('data_siswa', 'rombel_saat_ini')) {
            return;
        }

        $now = now();

        DB::table('data_siswa')
            ->select('rombel_saat_ini')
            ->whereNotNull('rombel_saat_ini')
            ->where('rombel_saat_ini', '!=', '')
            ->distinct()
            ->orderBy('rombel_saat_ini')
            ->pluck('rombel_saat_ini')
            ->map(fn (?string $name): string => Str::of((string) $name)->squish()->toString())
            ->filter()
            ->unique()
            ->each(function (string $name) use ($now): void {
                DB::table('rombels')->updateOrInsert(
                    ['nama' => $name],
                    [
                        'angkatan' => $this->extractAngkatan($name),
                        'is_active' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                );
            });
    }

    protected function extractAngkatan(string $rombel): ?string
    {
        if (preg_match('/(20\d{2}[\/-]20\d{2})/', $rombel, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function studentRombelCollation(): ?string
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return null;
        }

        if (! Schema::hasTable('data_siswa') || ! Schema::hasColumn('data_siswa', 'rombel_saat_ini')) {
            return null;
        }

        $collation = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'data_siswa')
            ->where('COLUMN_NAME', 'rombel_saat_ini')
            ->value('COLLATION_NAME');

        return is_string($collation) && preg_match('/^[A-Za-z0-9_]+$/', $collation)
            ? $collation
            : null;
    }
};
