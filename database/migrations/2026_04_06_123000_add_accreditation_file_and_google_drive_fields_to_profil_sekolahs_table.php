<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('profil_sekolahs')) {
            return;
        }

        Schema::table('profil_sekolahs', function (Blueprint $table): void {
            if (! Schema::hasColumn('profil_sekolahs', 'tanggal_berdiri')) {
                $table->date('tanggal_berdiri')->nullable()->after('tanggal_identitas');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'file_akreditasi_path')) {
                $table->string('file_akreditasi_path')->nullable()->after('organisasi_penyelenggara');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'gdrive_upload_status')) {
                $table->string('gdrive_upload_status', 40)->nullable()->after('file_akreditasi_path');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'gdrive_upload_progress')) {
                $table->unsignedTinyInteger('gdrive_upload_progress')->nullable()->after('gdrive_upload_status');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'gdrive_upload_message')) {
                $table->text('gdrive_upload_message')->nullable()->after('gdrive_upload_progress');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'gdrive_folder_id')) {
                $table->string('gdrive_folder_id', 120)->nullable()->after('gdrive_upload_message');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'gdrive_folder_url')) {
                $table->text('gdrive_folder_url')->nullable()->after('gdrive_folder_id');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'gdrive_file_id')) {
                $table->string('gdrive_file_id', 120)->nullable()->after('gdrive_folder_url');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'gdrive_file_url')) {
                $table->text('gdrive_file_url')->nullable()->after('gdrive_file_id');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'gdrive_last_sync_mode')) {
                $table->string('gdrive_last_sync_mode', 40)->nullable()->after('gdrive_file_url');
            }

            if (! Schema::hasColumn('profil_sekolahs', 'gdrive_uploaded_at')) {
                $table->timestamp('gdrive_uploaded_at')->nullable()->after('gdrive_last_sync_mode');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('profil_sekolahs')) {
            return;
        }

        Schema::table('profil_sekolahs', function (Blueprint $table): void {
            $columns = array_filter([
                Schema::hasColumn('profil_sekolahs', 'tanggal_berdiri') ? 'tanggal_berdiri' : null,
                Schema::hasColumn('profil_sekolahs', 'file_akreditasi_path') ? 'file_akreditasi_path' : null,
                Schema::hasColumn('profil_sekolahs', 'gdrive_upload_status') ? 'gdrive_upload_status' : null,
                Schema::hasColumn('profil_sekolahs', 'gdrive_upload_progress') ? 'gdrive_upload_progress' : null,
                Schema::hasColumn('profil_sekolahs', 'gdrive_upload_message') ? 'gdrive_upload_message' : null,
                Schema::hasColumn('profil_sekolahs', 'gdrive_folder_id') ? 'gdrive_folder_id' : null,
                Schema::hasColumn('profil_sekolahs', 'gdrive_folder_url') ? 'gdrive_folder_url' : null,
                Schema::hasColumn('profil_sekolahs', 'gdrive_file_id') ? 'gdrive_file_id' : null,
                Schema::hasColumn('profil_sekolahs', 'gdrive_file_url') ? 'gdrive_file_url' : null,
                Schema::hasColumn('profil_sekolahs', 'gdrive_last_sync_mode') ? 'gdrive_last_sync_mode' : null,
                Schema::hasColumn('profil_sekolahs', 'gdrive_uploaded_at') ? 'gdrive_uploaded_at' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
