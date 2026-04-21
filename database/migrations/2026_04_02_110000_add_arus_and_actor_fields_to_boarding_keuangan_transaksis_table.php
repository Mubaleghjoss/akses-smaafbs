<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boarding_keuangan_transaksis')) {
            return;
        }

        Schema::table('boarding_keuangan_transaksis', function (Blueprint $table): void {
            if (! Schema::hasColumn('boarding_keuangan_transaksis', 'arus')) {
                $table->string('arus', 20)->nullable()->after('jenis_transaksi');
                $table->index(['arus', 'tanggal_transaksi'], 'boarding_keuangan_arus_tanggal_index');
            }

            if (! Schema::hasColumn('boarding_keuangan_transaksis', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('keterangan');
                $table->index('created_by', 'boarding_keuangan_transaksi_created_by_index');
            }

            if (! Schema::hasColumn('boarding_keuangan_transaksis', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
                $table->index('updated_by', 'boarding_keuangan_transaksi_updated_by_index');
            }
        });

        if (! Schema::hasTable('boarding_keuangan_transaksi_histories')) {
            Schema::create('boarding_keuangan_transaksi_histories', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('boarding_keuangan_transaksi_id')->index();
                $table->string('aksi', 40);
                $table->string('judul_ringkas', 255)->nullable();
                $table->json('snapshot')->nullable();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name', 100)->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('boarding_keuangan_transaksi_histories')) {
            Schema::drop('boarding_keuangan_transaksi_histories');
        }

        if (! Schema::hasTable('boarding_keuangan_transaksis')) {
            return;
        }

        Schema::table('boarding_keuangan_transaksis', function (Blueprint $table): void {
            if (Schema::hasColumn('boarding_keuangan_transaksis', 'arus')) {
                $table->dropIndex('boarding_keuangan_arus_tanggal_index');
            }

            if (Schema::hasColumn('boarding_keuangan_transaksis', 'updated_by')) {
                $table->dropIndex('boarding_keuangan_transaksi_updated_by_index');
            }

            if (Schema::hasColumn('boarding_keuangan_transaksis', 'created_by')) {
                $table->dropIndex('boarding_keuangan_transaksi_created_by_index');
            }

            if (Schema::hasColumn('boarding_keuangan_transaksis', 'updated_by')) {
                $table->dropColumn('updated_by');
            }

            if (Schema::hasColumn('boarding_keuangan_transaksis', 'created_by')) {
                $table->dropColumn('created_by');
            }

            if (Schema::hasColumn('boarding_keuangan_transaksis', 'arus')) {
                $table->dropColumn('arus');
            }
        });
    }
};
