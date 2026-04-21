<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('boarding_pencapaians')) {
            $hasTargetSurat = Schema::hasColumn('boarding_pencapaians', 'target_jumlah_surat');
            $hasTargetDoa = Schema::hasColumn('boarding_pencapaians', 'target_jumlah_doa');
            $hasTargetHadits = Schema::hasColumn('boarding_pencapaians', 'target_jumlah_hadits');

            if (! $hasTargetSurat || ! $hasTargetDoa || ! $hasTargetHadits) {
                Schema::table('boarding_pencapaians', function (Blueprint $table) use ($hasTargetSurat, $hasTargetDoa, $hasTargetHadits): void {
                    if (! $hasTargetSurat) {
                        $table->unsignedInteger('target_jumlah_surat')->default(0)->after('jumlah_hadits_dihafal');
                    }

                    if (! $hasTargetDoa) {
                        $table->unsignedInteger('target_jumlah_doa')->default(0)->after('target_jumlah_surat');
                    }

                    if (! $hasTargetHadits) {
                        $table->unsignedInteger('target_jumlah_hadits')->default(0)->after('target_jumlah_doa');
                    }
                });
            }
        }

        if (! Schema::hasTable('boarding_pencapaian_updates')) {
            Schema::create('boarding_pencapaian_updates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('boarding_pencapaian_id')
                    ->constrained('boarding_pencapaians')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
                $table->date('tanggal_update');
                $table->string('kategori_update', 40);
                $table->string('judul_capaian', 150);
                $table->unsignedInteger('jumlah_tambahan')->default(0);
                $table->string('status_update', 30)->default('progres');
                $table->string('pamong_nama', 100)->nullable();
                $table->text('detail')->nullable();
                $table->timestamps();

                $table->index(['boarding_pencapaian_id', 'tanggal_update'], 'boarding_pencapaian_updates_owner_date_index');
                $table->index(['kategori_update', 'status_update'], 'boarding_pencapaian_updates_category_status_index');
            });
        }

        if (Schema::hasTable('boarding_rapots')) {
            $hasGeneratedAt = Schema::hasColumn('boarding_rapots', 'generated_at');
            $hasRekapPayload = Schema::hasColumn('boarding_rapots', 'rekap_payload');

            if (! $hasGeneratedAt || ! $hasRekapPayload) {
                Schema::table('boarding_rapots', function (Blueprint $table) use ($hasGeneratedAt, $hasRekapPayload): void {
                    if (! $hasGeneratedAt) {
                        $table->dateTime('generated_at')->nullable()->after('rekomendasi_tindak_lanjut');
                    }

                    if (! $hasRekapPayload) {
                        $table->json('rekap_payload')->nullable()->after('generated_at');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('boarding_rapots')) {
            $hasGeneratedAt = Schema::hasColumn('boarding_rapots', 'generated_at');
            $hasRekapPayload = Schema::hasColumn('boarding_rapots', 'rekap_payload');

            if ($hasGeneratedAt || $hasRekapPayload) {
                Schema::table('boarding_rapots', function (Blueprint $table) use ($hasGeneratedAt, $hasRekapPayload): void {
                    if ($hasRekapPayload) {
                        $table->dropColumn('rekap_payload');
                    }

                    if ($hasGeneratedAt) {
                        $table->dropColumn('generated_at');
                    }
                });
            }
        }

        if (Schema::hasTable('boarding_pencapaian_updates')) {
            Schema::drop('boarding_pencapaian_updates');
        }

        if (Schema::hasTable('boarding_pencapaians')) {
            $hasTargetSurat = Schema::hasColumn('boarding_pencapaians', 'target_jumlah_surat');
            $hasTargetDoa = Schema::hasColumn('boarding_pencapaians', 'target_jumlah_doa');
            $hasTargetHadits = Schema::hasColumn('boarding_pencapaians', 'target_jumlah_hadits');

            if ($hasTargetSurat || $hasTargetDoa || $hasTargetHadits) {
                Schema::table('boarding_pencapaians', function (Blueprint $table) use ($hasTargetSurat, $hasTargetDoa, $hasTargetHadits): void {
                    if ($hasTargetHadits) {
                        $table->dropColumn('target_jumlah_hadits');
                    }

                    if ($hasTargetDoa) {
                        $table->dropColumn('target_jumlah_doa');
                    }

                    if ($hasTargetSurat) {
                        $table->dropColumn('target_jumlah_surat');
                    }
                });
            }
        }
    }
};
