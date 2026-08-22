<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_materials')) {
            Schema::table('perpustakaan_literasi_materials', function (Blueprint $table): void {
                if (! Schema::hasColumn('perpustakaan_literasi_materials', 'program_category')) {
                    $table->string('program_category', 60)->nullable()->index()->after('slug');
                }

                if (! Schema::hasColumn('perpustakaan_literasi_materials', 'video_url')) {
                    $table->string('video_url', 1000)->nullable()->after('google_drive_url');
                }
            });
        }

        if (Schema::hasTable('perpustakaan_literasi_responses')) {
            Schema::table('perpustakaan_literasi_responses', function (Blueprint $table): void {
                if (! Schema::hasColumn('perpustakaan_literasi_responses', 'tab_switch_count')) {
                    $table->unsignedInteger('tab_switch_count')->default(0)->after('user_agent');
                }

                if (! Schema::hasColumn('perpustakaan_literasi_responses', 'app_hidden_count')) {
                    $table->unsignedInteger('app_hidden_count')->default(0)->after('tab_switch_count');
                }

                if (! Schema::hasColumn('perpustakaan_literasi_responses', 'page_leave_attempt_count')) {
                    $table->unsignedInteger('page_leave_attempt_count')->default(0)->after('app_hidden_count');
                }

                if (! Schema::hasColumn('perpustakaan_literasi_responses', 'last_integrity_event_at')) {
                    $table->timestamp('last_integrity_event_at')->nullable()->index()->after('page_leave_attempt_count');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_responses')) {
            Schema::table('perpustakaan_literasi_responses', function (Blueprint $table): void {
                foreach ([
                    'last_integrity_event_at',
                    'page_leave_attempt_count',
                    'app_hidden_count',
                    'tab_switch_count',
                ] as $column) {
                    if (Schema::hasColumn('perpustakaan_literasi_responses', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('perpustakaan_literasi_materials')) {
            Schema::table('perpustakaan_literasi_materials', function (Blueprint $table): void {
                foreach (['video_url', 'program_category'] as $column) {
                    if (Schema::hasColumn('perpustakaan_literasi_materials', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
