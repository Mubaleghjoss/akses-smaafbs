<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prestasis')) {
            return;
        }

        Schema::table('prestasis', function (Blueprint $table): void {
            if (! Schema::hasColumn('prestasis', 'gdrive_upload_status')) {
                $table->string('gdrive_upload_status', 40)->nullable()->index();
            }

            if (! Schema::hasColumn('prestasis', 'gdrive_upload_progress')) {
                $table->unsignedTinyInteger('gdrive_upload_progress')->nullable();
            }

            if (! Schema::hasColumn('prestasis', 'gdrive_upload_message')) {
                $table->text('gdrive_upload_message')->nullable();
            }

            if (! Schema::hasColumn('prestasis', 'gdrive_folder_id')) {
                $table->string('gdrive_folder_id', 120)->nullable();
            }

            if (! Schema::hasColumn('prestasis', 'gdrive_folder_url')) {
                $table->string('gdrive_folder_url', 2048)->nullable();
            }

            if (! Schema::hasColumn('prestasis', 'gdrive_file_id')) {
                $table->string('gdrive_file_id', 120)->nullable();
            }

            if (! Schema::hasColumn('prestasis', 'gdrive_file_url')) {
                $table->string('gdrive_file_url', 2048)->nullable();
            }

            if (! Schema::hasColumn('prestasis', 'gdrive_last_sync_mode')) {
                $table->string('gdrive_last_sync_mode', 40)->nullable()->index();
            }

            if (! Schema::hasColumn('prestasis', 'gdrive_assets_payload')) {
                $table->json('gdrive_assets_payload')->nullable();
            }

            if (! Schema::hasColumn('prestasis', 'gdrive_uploaded_at')) {
                $table->timestamp('gdrive_uploaded_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('prestasis')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('prestasis', 'gdrive_upload_status') ? 'gdrive_upload_status' : null,
            Schema::hasColumn('prestasis', 'gdrive_upload_progress') ? 'gdrive_upload_progress' : null,
            Schema::hasColumn('prestasis', 'gdrive_upload_message') ? 'gdrive_upload_message' : null,
            Schema::hasColumn('prestasis', 'gdrive_folder_id') ? 'gdrive_folder_id' : null,
            Schema::hasColumn('prestasis', 'gdrive_folder_url') ? 'gdrive_folder_url' : null,
            Schema::hasColumn('prestasis', 'gdrive_file_id') ? 'gdrive_file_id' : null,
            Schema::hasColumn('prestasis', 'gdrive_file_url') ? 'gdrive_file_url' : null,
            Schema::hasColumn('prestasis', 'gdrive_last_sync_mode') ? 'gdrive_last_sync_mode' : null,
            Schema::hasColumn('prestasis', 'gdrive_assets_payload') ? 'gdrive_assets_payload' : null,
            Schema::hasColumn('prestasis', 'gdrive_uploaded_at') ? 'gdrive_uploaded_at' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('prestasis', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
