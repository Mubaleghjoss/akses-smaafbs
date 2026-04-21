<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('komite_documents')) {
            return;
        }

        Schema::table('komite_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('komite_documents', 'gdrive_upload_status')) {
                $table->string('gdrive_upload_status', 40)->nullable()->after('catatan')->index();
            }

            if (! Schema::hasColumn('komite_documents', 'gdrive_upload_progress')) {
                $table->unsignedTinyInteger('gdrive_upload_progress')->nullable()->after('gdrive_upload_status');
            }

            if (! Schema::hasColumn('komite_documents', 'gdrive_upload_message')) {
                $table->text('gdrive_upload_message')->nullable()->after('gdrive_upload_progress');
            }

            if (! Schema::hasColumn('komite_documents', 'gdrive_folder_id')) {
                $table->string('gdrive_folder_id', 120)->nullable()->after('gdrive_upload_message');
            }

            if (! Schema::hasColumn('komite_documents', 'gdrive_folder_url')) {
                $table->string('gdrive_folder_url', 2048)->nullable()->after('gdrive_folder_id');
            }

            if (! Schema::hasColumn('komite_documents', 'gdrive_file_id')) {
                $table->string('gdrive_file_id', 120)->nullable()->after('gdrive_folder_url');
            }

            if (! Schema::hasColumn('komite_documents', 'gdrive_file_url')) {
                $table->string('gdrive_file_url', 2048)->nullable()->after('gdrive_file_id');
            }

            if (! Schema::hasColumn('komite_documents', 'gdrive_documentation_payload')) {
                $table->json('gdrive_documentation_payload')->nullable()->after('gdrive_file_url');
            }

            if (! Schema::hasColumn('komite_documents', 'gdrive_uploaded_at')) {
                $table->timestamp('gdrive_uploaded_at')->nullable()->after('gdrive_documentation_payload');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('komite_documents')) {
            return;
        }

        Schema::table('komite_documents', function (Blueprint $table): void {
            foreach ([
                'gdrive_uploaded_at',
                'gdrive_documentation_payload',
                'gdrive_file_url',
                'gdrive_file_id',
                'gdrive_folder_url',
                'gdrive_folder_id',
                'gdrive_upload_message',
                'gdrive_upload_progress',
                'gdrive_upload_status',
            ] as $column) {
                if (Schema::hasColumn('komite_documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
