<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rombels')
            || ! Schema::hasColumn('rombels', 'is_active')
            || ! Schema::hasTable('data_siswa')
            || ! Schema::hasColumn('data_siswa', 'rombel_saat_ini')
            || ! Schema::hasColumn('data_siswa', 'status')) {
            return;
        }

        DB::table('rombels')
            ->where('is_active', false)
            ->whereNotNull('nama')
            ->orderBy('id')
            ->pluck('nama')
            ->each(function (string $rombelName): void {
                $name = Str::upper(Str::squish($rombelName));
                [$status, $category] = match (true) {
                    Str::contains($name, 'ALUMNI') => ['alumni', 'lulus'],
                    Str::contains($name, 'MUTASI') => ['pindah', 'mutasi'],
                    Str::contains($name, 'MENGUNDURKAN') => ['keluar', 'mengundurkan_diri'],
                    Str::contains($name, 'WAFAT') => ['keluar', 'wafat'],
                    default => ['keluar', 'lainnya'],
                };

                $attributes = ['status' => $status];

                if (Schema::hasColumn('data_siswa', 'kategori_non_aktif')) {
                    $attributes['kategori_non_aktif'] = $category;
                }

                if (Schema::hasColumn('data_siswa', 'alasan_non_aktif')) {
                    $attributes['alasan_non_aktif'] = 'Otomatis nonaktif karena rombel '.Str::squish($rombelName).' dinonaktifkan.';
                }

                if (Schema::hasColumn('data_siswa', 'tanggal_non_aktif')) {
                    $attributes['tanggal_non_aktif'] = now()->toDateString();
                }

                if (Schema::hasColumn('data_siswa', 'updated_at')) {
                    $attributes['updated_at'] = now();
                }

                DB::table('data_siswa')
                    ->where('rombel_saat_ini', $rombelName)
                    ->where('status', 'aktif')
                    ->update($attributes);
            });
    }

    public function down(): void
    {
        // Status lama tidak dapat dipulihkan secara aman tanpa riwayat per siswa.
    }
};
