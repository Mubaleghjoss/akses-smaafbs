<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assessment_report_snapshots')
            || ! Schema::hasColumn('assessment_report_snapshots', 'delivery_mode')) {
            return;
        }

        DB::table('assessment_report_snapshots')
            ->where('generation_status', 'ready')
            ->where(function ($query): void {
                $query->whereNull('pdf_path')->orWhere('pdf_path', '');
            })
            ->update(['delivery_mode' => 'stream']);
    }

    public function down(): void
    {
        // Rekonsiliasi data ini tidak dibalik agar snapshot siap tetap dapat dirender.
    }
};
