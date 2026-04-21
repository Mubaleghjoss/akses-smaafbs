<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boarding_perizinan_siswas')) {
            return;
        }

        Schema::table('boarding_perizinan_siswas', function (Blueprint $table): void {
            if (! Schema::hasColumn('boarding_perizinan_siswas', 'diizinkan_oleh_user_id')) {
                $table->unsignedBigInteger('diizinkan_oleh_user_id')->nullable();
                $table->index('diizinkan_oleh_user_id', 'boarding_perizinan_diizinkan_user_index');
            }

            if (! Schema::hasColumn('boarding_perizinan_siswas', 'diizinkan_oleh_nama')) {
                $table->string('diizinkan_oleh_nama', 150)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('boarding_perizinan_siswas')) {
            return;
        }

        Schema::table('boarding_perizinan_siswas', function (Blueprint $table): void {
            if (Schema::hasColumn('boarding_perizinan_siswas', 'diizinkan_oleh_user_id')) {
                $table->dropIndex('boarding_perizinan_diizinkan_user_index');
                $table->dropColumn('diizinkan_oleh_user_id');
            }

            if (Schema::hasColumn('boarding_perizinan_siswas', 'diizinkan_oleh_nama')) {
                $table->dropColumn('diizinkan_oleh_nama');
            }
        });
    }
};
