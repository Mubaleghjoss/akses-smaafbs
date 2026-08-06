<?php

namespace App\Support\Perpustakaan;

use App\Models\PerpustakaanLiterasiSubmissionEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LiteracySubmissionEventRecorder
{
    private const ALLOWED_EVENTS = [
        'validation_failed',
        'server_error',
        'cancelled',
        'expired',
        'client_retry_exhausted',
        'unexpected_success_payload',
        'submission_rejected',
        'receipt_recovered',
        'throttled',
        'queue_deadlock_retry',
        'hosting_throttled',
    ];

    public function record(string $eventCode, array $attributes = []): void
    {
        if (! in_array($eventCode, self::ALLOWED_EVENTS, true)
            || ! Schema::hasTable('perpustakaan_literasi_submission_events')) {
            return;
        }

        try {
            PerpustakaanLiterasiSubmissionEvent::query()->create([
                'event_code' => $eventCode,
                'material_id' => $this->nullableInteger($attributes['material_id'] ?? null),
                'response_id' => $this->nullableInteger($attributes['response_id'] ?? null),
                'data_siswa_id' => $this->nullableInteger($attributes['data_siswa_id'] ?? null),
                'ticket_id' => $this->nullableInteger($attributes['ticket_id'] ?? null),
                'http_status' => $this->nullableInteger($attributes['http_status'] ?? null),
                'retry_statuses' => $this->retryStatuses($attributes['retry_statuses'] ?? []),
                'context' => $this->safeContext($attributes['context'] ?? []),
                'occurred_at' => $attributes['occurred_at'] ?? now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Literacy submission event could not be recorded.', [
                'event_code' => $eventCode,
                'exception' => $exception::class,
            ]);
        }
    }

    private function nullableInteger(mixed $value): ?int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer === false || $integer < 1 ? null : $integer;
    }

    private function retryStatuses(mixed $statuses): ?array
    {
        $values = collect(is_array($statuses) ? $statuses : explode(',', (string) $statuses))
            ->map(fn (mixed $status): string => trim((string) $status))
            ->filter(fn (string $status): bool => preg_match('/^(?:0|[1-5][0-9]{2})$/', $status) === 1)
            ->unique()
            ->take(12)
            ->values()
            ->all();

        return $values !== [] ? $values : null;
    }

    private function safeContext(mixed $context): ?array
    {
        if (! is_array($context)) {
            return null;
        }

        $allowed = collect($context)
            ->only([
                'operation',
                'fields',
                'queue_status',
                'reason',
                'request_kind',
                'exception',
                'content_type',
                'payload_status',
                'limiter_scope',
                'trace_id',
            ])
            ->map(function (mixed $value): mixed {
                if (is_array($value)) {
                    return collect($value)->map(fn (mixed $item): string => mb_substr((string) $item, 0, 100))->take(30)->all();
                }

                return mb_substr((string) $value, 0, 180);
            })
            ->all();

        return $allowed !== [] ? $allowed : null;
    }
}
