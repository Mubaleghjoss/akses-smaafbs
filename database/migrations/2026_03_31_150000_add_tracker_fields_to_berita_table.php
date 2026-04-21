<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('berita')) {
            return;
        }

        Schema::table('berita', function (Blueprint $table): void {
            if (! Schema::hasColumn('berita', 'tracker_phase')) {
                $table->string('tracker_phase', 20)->nullable()->after('tanggal_berita');
            }

            if (! Schema::hasColumn('berita', 'tracker_progress_percent')) {
                $table->unsignedTinyInteger('tracker_progress_percent')->nullable()->after('tracker_phase');
            }

            if (! Schema::hasColumn('berita', 'tracker_update_text')) {
                $table->text('tracker_update_text')->nullable()->after('tracker_progress_percent');
            }

            if (! Schema::hasColumn('berita', 'tracker_documentation_media')) {
                $table->json('tracker_documentation_media')->nullable()->after('tracker_update_text');
            }

            if (! Schema::hasColumn('berita', 'tracker_live_url')) {
                $table->string('tracker_live_url', 2048)->nullable()->after('tracker_documentation_media');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('berita')) {
            return;
        }

        Schema::table('berita', function (Blueprint $table): void {
            if (Schema::hasColumn('berita', 'tracker_live_url')) {
                $table->dropColumn('tracker_live_url');
            }

            if (Schema::hasColumn('berita', 'tracker_documentation_media')) {
                $table->dropColumn('tracker_documentation_media');
            }

            if (Schema::hasColumn('berita', 'tracker_update_text')) {
                $table->dropColumn('tracker_update_text');
            }

            if (Schema::hasColumn('berita', 'tracker_progress_percent')) {
                $table->dropColumn('tracker_progress_percent');
            }

            if (Schema::hasColumn('berita', 'tracker_phase')) {
                $table->dropColumn('tracker_phase');
            }
        });
    }
};
