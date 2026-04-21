<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('boarding_pencapaians')) {
            $hasPamongUserId = Schema::hasColumn('boarding_pencapaians', 'pamong_user_id');

            if (! $hasPamongUserId) {
                Schema::table('boarding_pencapaians', function (Blueprint $table): void {
                    $table->unsignedBigInteger('pamong_user_id')->nullable()->after('siswa_id');
                    $table->index('pamong_user_id', 'boarding_pencapaian_pamong_user_index');
                });
            }
        }

        if (! Schema::hasTable('boarding_pencapaian_details')) {
            Schema::create('boarding_pencapaian_details', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('boarding_pencapaian_id')
                    ->constrained('boarding_pencapaians')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
                $table->string('kategori_detail', 40);
                $table->string('nama_target', 150);
                $table->unsignedInteger('target_nilai')->default(1);
                $table->unsignedInteger('capaian_nilai')->default(0);
                $table->string('satuan', 40)->nullable();
                $table->string('status_detail', 30)->default('belum_mulai');
                $table->unsignedSmallInteger('urutan')->default(0);
                $table->date('tuntas_at')->nullable();
                $table->text('detail')->nullable();
                $table->timestamps();

                $table->index(['boarding_pencapaian_id', 'kategori_detail'], 'boarding_pencapaian_details_owner_category_index');
                $table->index(['status_detail', 'urutan'], 'boarding_pencapaian_details_status_order_index');
            });
        }

        if (Schema::hasTable('boarding_konseling_mts')) {
            $hasPamongUserId = Schema::hasColumn('boarding_konseling_mts', 'pamong_user_id');

            if (! $hasPamongUserId) {
                Schema::table('boarding_konseling_mts', function (Blueprint $table): void {
                    $table->unsignedBigInteger('pamong_user_id')->nullable()->after('siswa_id');
                    $table->index('pamong_user_id', 'boarding_konseling_pamong_user_index');
                });
            }
        }

        if (Schema::hasTable('boarding_keuangan_siswas')) {
            $hasPamongUserId = Schema::hasColumn('boarding_keuangan_siswas', 'pamong_user_id');

            if (! $hasPamongUserId) {
                Schema::table('boarding_keuangan_siswas', function (Blueprint $table): void {
                    $table->unsignedBigInteger('pamong_user_id')->nullable()->after('siswa_id');
                    $table->index('pamong_user_id', 'boarding_keuangan_pamong_user_index');
                });
            }
        }

        if (Schema::hasTable('boarding_rapots')) {
            $hasPamongUserId = Schema::hasColumn('boarding_rapots', 'pamong_user_id');
            $hasNomorDokumen = Schema::hasColumn('boarding_rapots', 'nomor_dokumen');
            $hasPredikatBoarding = Schema::hasColumn('boarding_rapots', 'predikat_boarding');
            $hasWaliPamongNama = Schema::hasColumn('boarding_rapots', 'wali_pamong_nama');
            $hasKepalaBoardingNama = Schema::hasColumn('boarding_rapots', 'kepala_boarding_nama');
            $hasMudirAsramaNama = Schema::hasColumn('boarding_rapots', 'mudir_asrama_nama');
            $hasTempatCetak = Schema::hasColumn('boarding_rapots', 'tempat_cetak');

            if (! $hasPamongUserId || ! $hasNomorDokumen || ! $hasPredikatBoarding || ! $hasWaliPamongNama || ! $hasKepalaBoardingNama || ! $hasMudirAsramaNama || ! $hasTempatCetak) {
                Schema::table('boarding_rapots', function (Blueprint $table) use ($hasPamongUserId, $hasNomorDokumen, $hasPredikatBoarding, $hasWaliPamongNama, $hasKepalaBoardingNama, $hasMudirAsramaNama, $hasTempatCetak): void {
                    if (! $hasPamongUserId) {
                        $table->unsignedBigInteger('pamong_user_id')->nullable()->after('siswa_id');
                        $table->index('pamong_user_id', 'boarding_rapot_pamong_user_index');
                    }

                    if (! $hasNomorDokumen) {
                        $table->string('nomor_dokumen', 50)->nullable()->after('status_rapot');
                    }

                    if (! $hasPredikatBoarding) {
                        $table->string('predikat_boarding', 50)->nullable()->after('nomor_dokumen');
                    }

                    if (! $hasWaliPamongNama) {
                        $table->string('wali_pamong_nama', 100)->nullable()->after('rekomendasi_tindak_lanjut');
                    }

                    if (! $hasKepalaBoardingNama) {
                        $table->string('kepala_boarding_nama', 100)->nullable()->after('wali_pamong_nama');
                    }

                    if (! $hasMudirAsramaNama) {
                        $table->string('mudir_asrama_nama', 100)->nullable()->after('kepala_boarding_nama');
                    }

                    if (! $hasTempatCetak) {
                        $table->string('tempat_cetak', 100)->nullable()->after('mudir_asrama_nama');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('boarding_rapots')) {
            Schema::table('boarding_rapots', function (Blueprint $table): void {
                foreach (['tempat_cetak', 'mudir_asrama_nama', 'kepala_boarding_nama', 'wali_pamong_nama', 'predikat_boarding', 'nomor_dokumen', 'pamong_user_id'] as $column) {
                    if (Schema::hasColumn('boarding_rapots', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('boarding_keuangan_siswas') && Schema::hasColumn('boarding_keuangan_siswas', 'pamong_user_id')) {
            Schema::table('boarding_keuangan_siswas', function (Blueprint $table): void {
                $table->dropColumn('pamong_user_id');
            });
        }

        if (Schema::hasTable('boarding_konseling_mts') && Schema::hasColumn('boarding_konseling_mts', 'pamong_user_id')) {
            Schema::table('boarding_konseling_mts', function (Blueprint $table): void {
                $table->dropColumn('pamong_user_id');
            });
        }

        if (Schema::hasTable('boarding_pencapaian_details')) {
            Schema::drop('boarding_pencapaian_details');
        }

        if (Schema::hasTable('boarding_pencapaians') && Schema::hasColumn('boarding_pencapaians', 'pamong_user_id')) {
            Schema::table('boarding_pencapaians', function (Blueprint $table): void {
                $table->dropColumn('pamong_user_id');
            });
        }
    }
};
