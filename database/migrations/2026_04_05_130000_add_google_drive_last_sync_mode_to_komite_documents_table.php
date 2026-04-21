<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('komite_documents') || Schema::hasColumn('komite_documents', 'gdrive_last_sync_mode')) {
            return;
        }

        Schema::table('komite_documents', function (Blueprint $table): void {
            $table->string('gdrive_last_sync_mode', 40)
                ->nullable()
                ->after('gdrive_file_url');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('komite_documents') || ! Schema::hasColumn('komite_documents', 'gdrive_last_sync_mode')) {
            return;
        }

        Schema::table('komite_documents', function (Blueprint $table): void {
            $table->dropColumn('gdrive_last_sync_mode');
        });
    }
};
