<?php

use App\Models\PerpustakaanLiterasiResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_responses')
            || ! Schema::hasTable('perpustakaan_literasi_submission_tickets')
            || ! Schema::hasColumn('perpustakaan_literasi_responses', 'submission_delivery_code')) {
            return;
        }

        DB::table('perpustakaan_literasi_submission_tickets')
            ->where('operation', 'create')
            ->where('status', 'completed')
            ->whereNotNull('result_response_id')
            ->orderBy('id')
            ->chunkById(200, function ($tickets): void {
                foreach ($tickets as $ticket) {
                    $waitSeconds = 0;

                    if ($ticket->requested_at !== null && $ticket->admitted_at !== null) {
                        $waitSeconds = max(0, (int) Carbon::parse($ticket->requested_at)
                            ->diffInSeconds(Carbon::parse($ticket->admitted_at)));
                    }

                    DB::table('perpustakaan_literasi_responses')
                        ->where('id', $ticket->result_response_id)
                        ->whereNull('submission_delivery_code')
                        ->update([
                            'submission_delivery_code' => $waitSeconds > 0
                                ? PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_QUEUED
                                : PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_DIRECT,
                            'submission_queue_wait_seconds' => $waitSeconds,
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Data lama yang sudah dapat dipetakan tidak dikosongkan kembali.
    }
};
