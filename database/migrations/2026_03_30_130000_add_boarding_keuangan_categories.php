<?php

use App\Models\BoardingKeuanganKategori;
use App\Models\BoardingKeuanganTransaksi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boarding_keuangan_kategoris')) {
            Schema::create('boarding_keuangan_kategoris', function (Blueprint $table): void {
                $table->id();
                $table->string('nama', 100);
                $table->string('slug', 100)->unique();
                $table->boolean('is_system')->default(false);
                $table->timestamps();

                $table->index(['is_system', 'nama'], 'boarding_keuangan_kategori_system_nama_index');
            });
        }

        BoardingKeuanganKategori::ensureBuiltinsSeeded();

        if (Schema::hasTable('boarding_keuangan_transaksis') && ! Schema::hasColumn('boarding_keuangan_transaksis', 'boarding_keuangan_kategori_id')) {
            Schema::table('boarding_keuangan_transaksis', function (Blueprint $table): void {
                $table->foreignId('boarding_keuangan_kategori_id')
                    ->nullable()
                    ->after('jenis_transaksi')
                    ->constrained('boarding_keuangan_kategoris', 'id', 'bk_trans_kategori_fk')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            });
        }

        $kategoriByLegacy = collect(BoardingKeuanganTransaksi::LEGACY_TYPE_TO_CATEGORY_SLUG)
            ->mapWithKeys(fn (string $slug, string $legacyType): array => [
                $legacyType => BoardingKeuanganKategori::idBySlug($slug),
            ])
            ->filter();

        foreach ($kategoriByLegacy as $legacyType => $categoryId) {
            DB::table('boarding_keuangan_transaksis')
                ->where('jenis_transaksi', $legacyType)
                ->whereNull('boarding_keuangan_kategori_id')
                ->update(['boarding_keuangan_kategori_id' => $categoryId]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('boarding_keuangan_transaksis') && Schema::hasColumn('boarding_keuangan_transaksis', 'boarding_keuangan_kategori_id')) {
            Schema::table('boarding_keuangan_transaksis', function (Blueprint $table): void {
                $table->dropForeign('bk_trans_kategori_fk');
                $table->dropColumn('boarding_keuangan_kategori_id');
            });
        }

        if (Schema::hasTable('boarding_keuangan_kategoris')) {
            Schema::drop('boarding_keuangan_kategoris');
        }
    }
};
