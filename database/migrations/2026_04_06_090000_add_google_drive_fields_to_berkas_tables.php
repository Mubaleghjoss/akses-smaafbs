<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addGoogleDriveColumns('berkas_siswa');
        $this->addGoogleDriveColumns('berkas_guru');
    }

    public function down(): void
    {
        $this->dropGoogleDriveColumns('berkas_siswa');
        $this->dropGoogleDriveColumns('berkas_guru');
    }

    private function addGoogleDriveColumns(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'gdrive_upload_status')) {
                $table->string('gdrive_upload_status', 40)->nullable()->index();
            }

            if (! Schema::hasColumn($tableName, 'gdrive_upload_progress')) {
                $table->unsignedTinyInteger('gdrive_upload_progress')->nullable();
            }

            if (! Schema::hasColumn($tableName, 'gdrive_upload_message')) {
                $table->text('gdrive_upload_message')->nullable();
            }

            if (! Schema::hasColumn($tableName, 'gdrive_folder_id')) {
                $table->string('gdrive_folder_id', 120)->nullable();
            }

            if (! Schema::hasColumn($tableName, 'gdrive_folder_url')) {
                $table->string('gdrive_folder_url', 2048)->nullable();
            }

            if (! Schema::hasColumn($tableName, 'gdrive_file_id')) {
                $table->string('gdrive_file_id', 120)->nullable();
            }

            if (! Schema::hasColumn($tableName, 'gdrive_file_url')) {
                $table->string('gdrive_file_url', 2048)->nullable();
            }

            if (! Schema::hasColumn($tableName, 'gdrive_last_sync_mode')) {
                $table->string('gdrive_last_sync_mode', 40)->nullable()->index();
            }

            if (! Schema::hasColumn($tableName, 'gdrive_uploaded_at')) {
                $table->timestamp('gdrive_uploaded_at')->nullable();
            }
        });
    }

    private function dropGoogleDriveColumns(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn($tableName, 'gdrive_upload_status') ? 'gdrive_upload_status' : null,
            Schema::hasColumn($tableName, 'gdrive_upload_progress') ? 'gdrive_upload_progress' : null,
            Schema::hasColumn($tableName, 'gdrive_upload_message') ? 'gdrive_upload_message' : null,
            Schema::hasColumn($tableName, 'gdrive_folder_id') ? 'gdrive_folder_id' : null,
            Schema::hasColumn($tableName, 'gdrive_folder_url') ? 'gdrive_folder_url' : null,
            Schema::hasColumn($tableName, 'gdrive_file_id') ? 'gdrive_file_id' : null,
            Schema::hasColumn($tableName, 'gdrive_file_url') ? 'gdrive_file_url' : null,
            Schema::hasColumn($tableName, 'gdrive_last_sync_mode') ? 'gdrive_last_sync_mode' : null,
            Schema::hasColumn($tableName, 'gdrive_uploaded_at') ? 'gdrive_uploaded_at' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
