<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_submission_tickets')) {
            return;
        }

        if (! Schema::hasColumn('perpustakaan_literasi_submission_tickets', 'request_key_hash')) {
            Schema::table('perpustakaan_literasi_submission_tickets', function (Blueprint $table): void {
                // Baris lama dibiarkan null. Tiket protokol v2 memakai hash unik ini
                // agar satu request browser tidak dapat membuat tiket berulang.
                $table->char('request_key_hash', 64)->nullable()->unique('perpus_lit_ticket_request_key_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_submission_tickets')
            && Schema::hasColumn('perpustakaan_literasi_submission_tickets', 'request_key_hash')) {
            Schema::table('perpustakaan_literasi_submission_tickets', function (Blueprint $table): void {
                $table->dropUnique('perpus_lit_ticket_request_key_unique');
                $table->dropColumn('request_key_hash');
            });
        }
    }
};
