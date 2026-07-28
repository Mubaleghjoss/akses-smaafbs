<?php

namespace App\Support\Perpustakaan;

use App\Models\PerpustakaanLiterasiNetworkCheck;
use App\Models\PerpustakaanLiterasiResponse;
use App\Models\PerpustakaanLiterasiSubmissionEvent;
use App\Models\PerpustakaanLiterasiSubmissionQueueState;
use App\Models\PerpustakaanLiterasiSubmissionTicket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LiteracyOperationalHealth
{
    public function snapshot(): array
    {
        if (app()->environment('testing')) {
            return $this->buildSnapshot();
        }

        return Cache::remember('literacy:operational-health:v1', now()->addSeconds(15), fn (): array => $this->buildSnapshot());
    }

    private function buildSnapshot(): array
    {
        $since = now()->subDay();
        $ticketCounts = collect();
        $eventCounts = collect();
        $deliveryCounts = collect();
        $state = null;
        $network = null;

        if (Schema::hasTable('perpustakaan_literasi_submission_tickets')) {
            $ticketCounts = PerpustakaanLiterasiSubmissionTicket::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');
        }

        if (Schema::hasTable('perpustakaan_literasi_submission_events')) {
            $eventCounts = PerpustakaanLiterasiSubmissionEvent::query()
                ->where('occurred_at', '>=', $since)
                ->selectRaw('event_code, count(*) as aggregate')
                ->groupBy('event_code')
                ->pluck('aggregate', 'event_code');
        }

        if (Schema::hasTable('perpustakaan_literasi_responses')
            && Schema::hasColumn('perpustakaan_literasi_responses', 'submission_delivery_code')) {
            $deliveryCounts = PerpustakaanLiterasiResponse::withTrashed()
                ->where('submitted_at', '>=', $since)
                ->selectRaw('submission_delivery_code, count(*) as aggregate')
                ->groupBy('submission_delivery_code')
                ->pluck('aggregate', 'submission_delivery_code');
        }

        if (Schema::hasTable('perpustakaan_literasi_submission_queue_states')) {
            $state = PerpustakaanLiterasiSubmissionQueueState::query()->find(LiteracySubmissionQueue::SCOPE);
        }

        if (Schema::hasTable('perpustakaan_literasi_network_checks')) {
            $network = PerpustakaanLiterasiNetworkCheck::query()->latest('checked_at')->first();
        }

        $pendingJobs = Schema::hasTable('jobs')
            ? DB::table('jobs')->where('queue', config('literacy.similarity_queue', 'literacy-analysis'))->count()
            : 0;
        $failedJobs = Schema::hasTable('failed_jobs') && Schema::hasColumn('failed_jobs', 'queue')
            ? DB::table('failed_jobs')->where('queue', config('literacy.similarity_queue', 'literacy-analysis'))->count()
            : 0;
        $schedulerHealthy = $state?->scheduler_heartbeat_at?->greaterThan(now()->subMinutes(3)) ?? false;
        $networkStaleMinutes = (int) config('literacy.school_monitor.stale_minutes', 10);
        $networkFresh = $network?->checked_at?->greaterThan(now()->subMinutes($networkStaleMinutes)) ?? false;
        $networkHealthy = $networkFresh && in_array($network?->status, ['ok', 'recovered'], true);

        return [
            'queue_enabled' => (bool) config('literacy.submission_queue.enabled', true),
            'active_slots' => (int) config('literacy.submission_queue.active_slots', 10),
            'waiting' => (int) ($ticketCounts[PerpustakaanLiterasiSubmissionTicket::STATUS_WAITING] ?? 0),
            'active' => (int) ($ticketCounts[PerpustakaanLiterasiSubmissionTicket::STATUS_ADMITTED] ?? 0)
                + (int) ($ticketCounts[PerpustakaanLiterasiSubmissionTicket::STATUS_PROCESSING] ?? 0),
            'average_seconds' => round(((int) ($state?->average_duration_ms ?? 0)) / 1000, 1),
            'direct_24h' => (int) ($deliveryCounts[PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_DIRECT] ?? 0),
            'queued_24h' => (int) ($deliveryCounts[PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_QUEUED] ?? 0),
            'retry_429_24h' => (int) ($deliveryCounts[PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_RETRY_429] ?? 0),
            'retry_503_24h' => (int) ($deliveryCounts[PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_RETRY_503] ?? 0),
            'validation_failed_24h' => (int) ($eventCounts['validation_failed'] ?? 0),
            'retry_exhausted_24h' => (int) ($eventCounts['client_retry_exhausted'] ?? 0),
            'cancelled_24h' => (int) ($eventCounts['cancelled'] ?? 0),
            'expired_24h' => (int) ($eventCounts['expired'] ?? 0),
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
            'scheduler_healthy' => $schedulerHealthy,
            'scheduler_label' => $state?->scheduler_heartbeat_at
                ? $state->scheduler_heartbeat_at->locale('id')->diffForHumans()
                : 'Belum tercatat',
            'worker_status' => $state?->worker_status ?: 'belum tercatat',
            'network_healthy' => $networkHealthy,
            'network_status' => $network?->status ?: 'belum ada data',
            'network_label' => $network?->checked_at
                ? $network->checked_at->locale('id')->diffForHumans()
                : 'Pasang monitor sekolah',
            'network_duration_ms' => $network?->duration_ms,
            'network_error_code' => $network?->error_code,
            'updated_at' => now(),
        ];
    }
}
