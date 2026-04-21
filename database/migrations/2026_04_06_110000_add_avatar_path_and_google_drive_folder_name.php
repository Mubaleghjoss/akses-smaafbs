<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jenis_berkas')) {
            Schema::table('jenis_berkas', function (Blueprint $table): void {
                if (! Schema::hasColumn('jenis_berkas', 'google_drive_folder_name')) {
                    $table->string('google_drive_folder_name', 150)->nullable()->after('nama_berkas');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'avatar_path')) {
                    $table->string('avatar_path')->nullable()->after('email');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('jenis_berkas') && Schema::hasColumn('jenis_berkas', 'google_drive_folder_name')) {
            Schema::table('jenis_berkas', function (Blueprint $table): void {
                $table->dropColumn('google_drive_folder_name');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'avatar_path')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('avatar_path');
            });
        }
    }
};
