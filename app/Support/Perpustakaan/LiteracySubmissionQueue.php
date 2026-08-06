<?php

namespace App\Support\Perpustakaan;

use App\Exceptions\LiteracySubmissionQueueBusy;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiResponse;
use App\Models\PerpustakaanLiterasiSubmissionQueueState;
use App\Models\PerpustakaanLiterasiSubmissionTicket;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LiteracySubmissionQueue
{
    public const SCOPE = 'literacy';

    protected ?bool $activityColumnAvailable = null;

    public function enabled(): bool
    {
        return (bool) config('literacy.submission_queue.enabled', true)
            && Schema::hasTable('perpustakaan_literasi_submission_tickets')
            && Schema::hasTable('perpustakaan_literasi_submission_queue_states');
    }

    public function requestNewTicket(
        Request $request,
        PerpustakaanLiterasiMaterial $material,
        int $studentId,
        string $requestId,
    ): ?PerpustakaanLiterasiSubmissionTicket {
        return $this->requestTicket(
            $request,
            'create',
            'create:'.$material->getKey().':'.$studentId.':'.$requestId,
            $material->getKey(),
            null,
            $studentId,
        );
    }

    public function requestEditTicket(
        Request $request,
        PerpustakaanLiterasiResponse $response,
        string $requestId,
    ): ?PerpustakaanLiterasiSubmissionTicket {
        return $this->requestTicket(
            $request,
            'update',
            'update:'.$response->getKey().':'.$requestId,
            $response->material_id,
            $response->getKey(),
            $response->data_siswa_id,
        );
    }

    public function claimNewSubmission(
        Request $request,
        PerpustakaanLiterasiMaterial $material,
        int $studentId,
        string $requestId,
        ?string $token,
    ): ?PerpustakaanLiterasiSubmissionTicket {
        if (! $this->enabled()) {
            return null;
        }

        $ticket = filled($token) ? $this->findOwnedTicket($request, (string) $token) : null;

        if (! $ticket || in_array($ticket->status, [
            PerpustakaanLiterasiSubmissionTicket::STATUS_CANCELLED,
            PerpustakaanLiterasiSubmissionTicket::STATUS_EXPIRED,
        ], true)) {
            $ticket = $this->requestNewTicket($request, $material, $studentId, $requestId);
        }

        return $this->claim($ticket, 'create:'.$material->getKey().':'.$studentId.':'.$requestId);
    }

    public function claimEditSubmission(
        Request $request,
        PerpustakaanLiterasiResponse $response,
        string $requestId,
        ?string $token,
    ): ?PerpustakaanLiterasiSubmissionTicket {
        if (! $this->enabled()) {
            return null;
        }

        $ticket = filled($token) ? $this->findOwnedTicket($request, (string) $token) : null;

        if (! $ticket || in_array($ticket->status, [
            PerpustakaanLiterasiSubmissionTicket::STATUS_CANCELLED,
            PerpustakaanLiterasiSubmissionTicket::STATUS_EXPIRED,
        ], true)) {
            $ticket = $this->requestEditTicket($request, $response, $requestId);
        }

        return $this->claim($ticket, 'update:'.$response->getKey().':'.$requestId);
    }

    public function status(Request $request, string $token): array
    {
        if (! $this->enabled()) {
            return $this->disabledPayload();
        }

        $ticket = $this->findOwnedTicket($request, $token);

        abort_unless($ticket, 404);

        if ($ticket->status === PerpustakaanLiterasiSubmissionTicket::STATUS_WAITING
            || ($ticket->expires_at?->isPast() && in_array($ticket->status, [
                PerpustakaanLiterasiSubmissionTicket::STATUS_ADMITTED,
                PerpustakaanLiterasiSubmissionTicket::STATUS_PROCESSING,
            ], true))) {
            $this->tryPromoteWaitingTickets();
            $ticket->refresh();
        }

        return $this->payload($ticket, $this->state());
    }

    public function cancel(Request $request, string $token): array
    {
        if (! $this->enabled()) {
            return $this->disabledPayload();
        }

        $changed = false;
        $ticket = DB::transaction(function () use ($request, $token, &$changed): PerpustakaanLiterasiSubmissionTicket {
            $ticket = PerpustakaanLiterasiSubmissionTicket::query()
                ->where('public_token', $token)
                ->whereIn('owner_hash', $this->ownerHashes($request))
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($ticket->status, [
                PerpustakaanLiterasiSubmissionTicket::STATUS_COMPLETED,
                PerpustakaanLiterasiSubmissionTicket::STATUS_EXPIRED,
                PerpustakaanLiterasiSubmissionTicket::STATUS_CANCELLED,
            ], true)) {
                $ticket->forceFill([
                    'status' => PerpustakaanLiterasiSubmissionTicket::STATUS_CANCELLED,
                    'expires_at' => now()->addHours($this->completedTtlHours()),
                ])->save();
                $changed = true;
            }

            return $ticket;
        }, 3);

        $this->markSubmissionActivity();

        if ($changed) {
            app(LiteracySubmissionEventRecorder::class)->record('cancelled', [
                'material_id' => $ticket->material_id,
                'response_id' => $ticket->response_id,
                'data_siswa_id' => $ticket->data_siswa_id,
                'ticket_id' => $ticket->getKey(),
                'context' => [
                    'operation' => $ticket->operation,
                    'queue_status' => PerpustakaanLiterasiSubmissionTicket::STATUS_CANCELLED,
                ],
            ]);
        }

        $this->tryPromoteWaitingTickets();

        return $this->payload($ticket->refresh(), $this->state());
    }

    public function complete(
        ?PerpustakaanLiterasiSubmissionTicket $ticket,
        ?PerpustakaanLiterasiResponse $response = null,
    ): void {
        if (! $ticket || ! $this->enabled()) {
            return;
        }

        DB::transaction(fn () => $this->completeWithinTransaction($ticket, $response), 3);
        $this->afterCompletion($ticket->refresh());
    }

    public function completeWithinTransaction(
        ?PerpustakaanLiterasiSubmissionTicket $ticket,
        ?PerpustakaanLiterasiResponse $response = null,
    ): void {
        if (! $ticket || ! $this->enabled()) {
            return;
        }

        $current = PerpustakaanLiterasiSubmissionTicket::query()
            ->whereKey($ticket->getKey())
            ->lockForUpdate()
            ->first();

        if (! $current || $current->status === PerpustakaanLiterasiSubmissionTicket::STATUS_COMPLETED) {
            return;
        }

        $current->forceFill([
            'status' => PerpustakaanLiterasiSubmissionTicket::STATUS_COMPLETED,
            'completed_at' => now(),
            'expires_at' => now()->addHours($this->completedTtlHours()),
            'result_response_id' => $response?->getKey(),
        ])->save();
    }

    public function afterCompletion(?PerpustakaanLiterasiSubmissionTicket $ticket): void
    {
        if (! $ticket || ! $this->enabled()) {
            return;
        }

        $this->markSubmissionActivity();

        if ($ticket->started_at) {
            $state = $this->state();
            $durationMs = max(1, (int) $ticket->started_at->diffInMilliseconds(now()));
            $averageMs = (int) round(((int) $state->average_duration_ms * 0.8) + ($durationMs * 0.2));

            PerpustakaanLiterasiSubmissionQueueState::query()
                ->whereKey(self::SCOPE)
                ->update([
                    'average_duration_ms' => min(30000, max(250, $averageMs)),
                    'updated_at' => now(),
                ]);
        }

        $this->tryPromoteWaitingTickets();
    }

    public function release(?PerpustakaanLiterasiSubmissionTicket $ticket): void
    {
        if (! $ticket || ! $this->enabled()) {
            return;
        }

        DB::transaction(function () use ($ticket): void {
            PerpustakaanLiterasiSubmissionTicket::query()
                ->whereKey($ticket->getKey())
                ->whereIn('status', [
                    PerpustakaanLiterasiSubmissionTicket::STATUS_ADMITTED,
                    PerpustakaanLiterasiSubmissionTicket::STATUS_PROCESSING,
                ])
                ->update([
                    'status' => PerpustakaanLiterasiSubmissionTicket::STATUS_CANCELLED,
                    'expires_at' => now()->addHours($this->completedTtlHours()),
                    'updated_at' => now(),
                ]);

        }, 3);

        $this->markSubmissionActivity();
        $this->tryPromoteWaitingTickets();
    }

    public function payloadFor(?PerpustakaanLiterasiSubmissionTicket $ticket): array
    {
        if (! $ticket || ! $this->enabled()) {
            return $this->disabledPayload();
        }

        return $this->payload($ticket->fresh(), $this->state());
    }

    protected function requestTicket(
        Request $request,
        string $operation,
        string $operationKey,
        ?int $materialId,
        ?int $responseId,
        ?int $studentId,
    ): ?PerpustakaanLiterasiSubmissionTicket {
        if (! $this->enabled()) {
            return null;
        }

        $requestKeyHash = hash('sha256', $this->ownerHash($request).'|'.$operationKey);

        $ticket = DB::transaction(function () use ($request, $operation, $operationKey, $materialId, $responseId, $studentId, $requestKeyHash): PerpustakaanLiterasiSubmissionTicket {
            $ownerHash = $this->ownerHash($request);
            $ticket = PerpustakaanLiterasiSubmissionTicket::query()
                ->where('request_key_hash', $requestKeyHash)
                ->orWhere(function ($query) use ($ownerHash, $operationKey): void {
                    $query
                        ->whereNull('request_key_hash')
                        ->where('owner_hash', $ownerHash)
                        ->where('operation_key', $operationKey);
                })
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($ticket && in_array($ticket->status, [
                PerpustakaanLiterasiSubmissionTicket::STATUS_CANCELLED,
                PerpustakaanLiterasiSubmissionTicket::STATUS_EXPIRED,
            ], true) && ! $ticket->result_response_id) {
                $ticket->forceFill([
                    'request_key_hash' => $ticket->request_key_hash ?: $requestKeyHash,
                    'status' => PerpustakaanLiterasiSubmissionTicket::STATUS_WAITING,
                    'requested_at' => now(),
                    'admitted_at' => null,
                    'started_at' => null,
                    'completed_at' => null,
                    'expires_at' => now()->addMinutes($this->waitTtlMinutes()),
                ])->save();

                return $ticket;
            }

            if ($ticket) {
                return $ticket;
            }

            PerpustakaanLiterasiSubmissionTicket::query()->insertOrIgnore([
                'public_token' => Str::random(64),
                'request_key_hash' => $requestKeyHash,
                'scope' => self::SCOPE,
                'owner_hash' => $ownerHash,
                'operation_key' => $operationKey,
                'operation' => $operation,
                'material_id' => $materialId,
                'response_id' => $responseId,
                'data_siswa_id' => $studentId,
                'status' => PerpustakaanLiterasiSubmissionTicket::STATUS_WAITING,
                'requested_at' => now(),
                'expires_at' => now()->addMinutes($this->waitTtlMinutes()),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return PerpustakaanLiterasiSubmissionTicket::query()
                ->where('request_key_hash', $requestKeyHash)
                ->firstOrFail();
        }, 3);

        $this->markSubmissionActivity();
        $this->tryPromoteWaitingTickets();

        return $ticket->refresh();
    }

    protected function claim(
        ?PerpustakaanLiterasiSubmissionTicket $ticket,
        string $expectedOperationKey,
    ): ?PerpustakaanLiterasiSubmissionTicket {
        if (! $ticket || ! $this->enabled()) {
            return null;
        }

        $this->markSubmissionActivity();
        $this->tryPromoteWaitingTickets();

        return DB::transaction(function () use ($ticket, $expectedOperationKey): PerpustakaanLiterasiSubmissionTicket {
            $current = PerpustakaanLiterasiSubmissionTicket::query()
                ->whereKey($ticket->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! hash_equals($expectedOperationKey, (string) $current->operation_key)) {
                abort(422, 'Tiket antrean tidak cocok dengan pengiriman ini.');
            }

            if ($current->status === PerpustakaanLiterasiSubmissionTicket::STATUS_COMPLETED) {
                return $current;
            }

            if ($current->status !== PerpustakaanLiterasiSubmissionTicket::STATUS_ADMITTED) {
                throw new LiteracySubmissionQueueBusy($this->payload($current, $this->state()));
            }

            $current->forceFill([
                'status' => PerpustakaanLiterasiSubmissionTicket::STATUS_PROCESSING,
                'started_at' => now(),
                'expires_at' => now()->addSeconds($this->processingTtlSeconds()),
            ])->save();

            return $current;
        }, 3);
    }

    protected function findOwnedTicket(Request $request, string $token): ?PerpustakaanLiterasiSubmissionTicket
    {
        if (! $this->enabled()) {
            abort(404);
        }

        return PerpustakaanLiterasiSubmissionTicket::query()
            ->where('public_token', $token)
            ->whereIn('owner_hash', $this->ownerHashes($request))
            ->first();
    }

    public function ownedTicketForRequest(
        Request $request,
        string $token,
        string $requestId,
    ): PerpustakaanLiterasiSubmissionTicket {
        $ticket = $this->findOwnedTicket($request, $token);

        abort_unless($ticket, 404);
        abort_unless(Str::endsWith((string) $ticket->operation_key, ':'.$requestId), 422, 'Request ID tidak cocok dengan tiket pengiriman.');

        return $ticket;
    }

    protected function state(): PerpustakaanLiterasiSubmissionQueueState
    {
        $state = PerpustakaanLiterasiSubmissionQueueState::query()
            ->whereKey(self::SCOPE)
            ->first();

        if ($state) {
            return $state;
        }

        $this->ensureState();

        return PerpustakaanLiterasiSubmissionQueueState::query()
            ->whereKey(self::SCOPE)
            ->firstOrFail();
    }

    public function analysisShouldWait(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $hasActiveTickets = PerpustakaanLiterasiSubmissionTicket::query()
            ->where('scope', self::SCOPE)
            ->whereIn('status', [
                PerpustakaanLiterasiSubmissionTicket::STATUS_WAITING,
                PerpustakaanLiterasiSubmissionTicket::STATUS_ADMITTED,
                PerpustakaanLiterasiSubmissionTicket::STATUS_PROCESSING,
            ])
            ->where('expires_at', '>', now())
            ->exists();

        if ($hasActiveTickets) {
            return true;
        }

        if (! $this->activityColumnAvailable()) {
            return false;
        }

        return PerpustakaanLiterasiSubmissionQueueState::query()
            ->whereKey(self::SCOPE)
            ->where('last_submission_activity_at', '>=', now()->subSeconds($this->analysisIdleSeconds()))
            ->exists();
    }

    protected function markSubmissionActivity(): void
    {
        if (! $this->activityColumnAvailable()) {
            return;
        }

        $this->ensureState();
        PerpustakaanLiterasiSubmissionQueueState::query()
            ->whereKey(self::SCOPE)
            ->update([
                'last_submission_activity_at' => now(),
                'updated_at' => now(),
            ]);
    }

    protected function ensureState(): void
    {
        DB::table('perpustakaan_literasi_submission_queue_states')->insertOrIgnore([
            'scope' => self::SCOPE,
            'average_duration_ms' => 2000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function activityColumnAvailable(): bool
    {
        return $this->activityColumnAvailable ??= Schema::hasColumn(
            'perpustakaan_literasi_submission_queue_states',
            'last_submission_activity_at',
        );
    }

    protected function expireStaleTickets(): void
    {
        $expiredTickets = PerpustakaanLiterasiSubmissionTicket::query()
            ->where('scope', self::SCOPE)
            ->whereIn('status', [
                PerpustakaanLiterasiSubmissionTicket::STATUS_WAITING,
                PerpustakaanLiterasiSubmissionTicket::STATUS_ADMITTED,
                PerpustakaanLiterasiSubmissionTicket::STATUS_PROCESSING,
            ])
            ->where('expires_at', '<=', now())
            ->get([
                'id',
                'material_id',
                'response_id',
                'data_siswa_id',
                'operation',
                'status',
            ]);

        if ($expiredTickets->isNotEmpty()) {
            PerpustakaanLiterasiSubmissionTicket::query()
                ->whereKey($expiredTickets->pluck('id'))
                ->update([
                    'status' => PerpustakaanLiterasiSubmissionTicket::STATUS_EXPIRED,
                    'expires_at' => now()->addHours($this->completedTtlHours()),
                    'updated_at' => now(),
                ]);

            $expiredTickets->each(function (PerpustakaanLiterasiSubmissionTicket $ticket): void {
                app(LiteracySubmissionEventRecorder::class)->record('expired', [
                    'material_id' => $ticket->material_id,
                    'response_id' => $ticket->response_id,
                    'data_siswa_id' => $ticket->data_siswa_id,
                    'ticket_id' => $ticket->getKey(),
                    'context' => [
                        'operation' => $ticket->operation,
                        'queue_status' => $ticket->status,
                    ],
                ]);
            });
        }

        PerpustakaanLiterasiSubmissionTicket::query()
            ->whereIn('status', [
                PerpustakaanLiterasiSubmissionTicket::STATUS_COMPLETED,
                PerpustakaanLiterasiSubmissionTicket::STATUS_CANCELLED,
                PerpustakaanLiterasiSubmissionTicket::STATUS_EXPIRED,
            ])
            ->where('expires_at', '<=', now())
            ->delete();
    }

    protected function tryPromoteWaitingTickets(): void
    {
        $lock = Cache::lock('literacy:submission-admission:v2', 5);

        if (! $lock->get()) {
            return;
        }

        try {
            try {
                DB::transaction(function (): void {
                    $this->expireStaleTickets();
                    $this->promoteWaitingTickets();
                }, 3);
            } catch (QueryException $exception) {
                if (! in_array((string) $exception->getCode(), ['40001', '40P01'], true)) {
                    throw $exception;
                }

                app(LiteracySubmissionEventRecorder::class)->record('queue_deadlock_retry', [
                    'http_status' => 425,
                    'context' => [
                        'operation' => 'promote',
                        'reason' => 'deadlock_after_database_retry',
                        'exception' => $exception::class,
                    ],
                ]);
            }
        } finally {
            $lock->release();
        }
    }

    protected function promoteWaitingTickets(): void
    {
        $active = PerpustakaanLiterasiSubmissionTicket::query()
            ->where('scope', self::SCOPE)
            ->whereIn('status', [
                PerpustakaanLiterasiSubmissionTicket::STATUS_ADMITTED,
                PerpustakaanLiterasiSubmissionTicket::STATUS_PROCESSING,
            ])
            ->count();

        $available = max(0, $this->activeSlots() - $active);

        if ($available === 0) {
            return;
        }

        PerpustakaanLiterasiSubmissionTicket::query()
            ->where('scope', self::SCOPE)
            ->where('status', PerpustakaanLiterasiSubmissionTicket::STATUS_WAITING)
            ->orderBy('requested_at')
            ->orderBy('id')
            ->limit($available)
            ->lockForUpdate()
            ->get()
            ->each(function (PerpustakaanLiterasiSubmissionTicket $ticket): void {
                $ticket->forceFill([
                    'status' => PerpustakaanLiterasiSubmissionTicket::STATUS_ADMITTED,
                    'admitted_at' => now(),
                    'expires_at' => now()->addSeconds($this->admissionTtlSeconds()),
                ])->save();
            });
    }

    protected function payload(
        PerpustakaanLiterasiSubmissionTicket $ticket,
        PerpustakaanLiterasiSubmissionQueueState $state,
    ): array {
        $position = 0;

        if ($ticket->status === PerpustakaanLiterasiSubmissionTicket::STATUS_WAITING) {
            $position = PerpustakaanLiterasiSubmissionTicket::query()
                ->where('scope', self::SCOPE)
                ->where('status', PerpustakaanLiterasiSubmissionTicket::STATUS_WAITING)
                ->where(function ($query) use ($ticket): void {
                    $query
                        ->where('requested_at', '<', $ticket->requested_at)
                        ->orWhere(function ($sameTime) use ($ticket): void {
                            $sameTime
                                ->where('requested_at', $ticket->requested_at)
                                ->where('id', '<=', $ticket->getKey());
                        });
                })
                ->count();
        }

        $averageSeconds = max(1, (int) ceil($state->average_duration_ms / 1000));
        $retryAfterSeconds = $this->retryAfterSeconds($position);
        $estimatedSeconds = $position > 0
            ? max($retryAfterSeconds, (int) ceil($position / $this->activeSlots()) * $averageSeconds)
            : 0;

        $payload = [
            'ticket' => $ticket->public_token,
            'status' => $ticket->status,
            'position' => $position,
            'estimated_wait_seconds' => $estimatedSeconds,
            'retry_after_seconds' => $retryAfterSeconds,
            'expires_at' => $ticket->expires_at?->toIso8601String(),
            'status_url' => route('library.literacy.queue.status', $ticket->public_token),
            'cancel_url' => route('library.literacy.queue.cancel', $ticket->public_token),
        ];

        if ($ticket->status === PerpustakaanLiterasiSubmissionTicket::STATUS_COMPLETED) {
            $payload['receipt_recovery_url'] = route('library.literacy.queue.receipt', $ticket->public_token);
        }

        return $payload;
    }

    protected function disabledPayload(): array
    {
        return [
            'ticket' => null,
            'status' => PerpustakaanLiterasiSubmissionTicket::STATUS_ADMITTED,
            'position' => 0,
            'estimated_wait_seconds' => 0,
            'retry_after_seconds' => 0,
            'expires_at' => null,
            'status_url' => null,
            'cancel_url' => null,
            'receipt_recovery_url' => null,
        ];
    }

    protected function ownerHash(Request $request): string
    {
        $ownerKey = (string) $request->session()->get('literacy_submission_owner_key', '');

        if ($ownerKey === '') {
            $ownerKey = Str::random(64);
            $request->session()->put('literacy_submission_owner_key', $ownerKey);
        }

        return hash('sha256', $ownerKey);
    }

    /**
     * @return array<int, string>
     */
    protected function ownerHashes(Request $request): array
    {
        return array_values(array_unique([
            $this->ownerHash($request),
            // Kompatibilitas tiket async-v1 yang memakai hash ID sesi.
            hash('sha256', $request->session()->getId()),
        ]));
    }

    protected function activeSlots(): int
    {
        return max(1, (int) config('literacy.submission_queue.active_slots', 10));
    }

    protected function pollSeconds(): int
    {
        return max(2, (int) config('literacy.submission_queue.poll_seconds', 5));
    }

    protected function retryAfterSeconds(int $position): int
    {
        if ($position > (int) config('literacy.submission_queue.poll_far_position', 100)) {
            return max($this->pollSeconds(), (int) config('literacy.submission_queue.poll_far_seconds', 25));
        }

        if ($position > (int) config('literacy.submission_queue.poll_middle_position', 30)) {
            return max($this->pollSeconds(), (int) config('literacy.submission_queue.poll_middle_seconds', 12));
        }

        return $this->pollSeconds();
    }

    protected function waitTtlMinutes(): int
    {
        return max(1, (int) config('literacy.submission_queue.wait_ttl_minutes', 10));
    }

    protected function admissionTtlSeconds(): int
    {
        return max(30, (int) config('literacy.submission_queue.admission_ttl_seconds', 60));
    }

    protected function analysisIdleSeconds(): int
    {
        return max(30, (int) config('literacy.submission_queue.analysis_idle_seconds', 180));
    }

    protected function processingTtlSeconds(): int
    {
        return max(30, (int) config('literacy.submission_queue.processing_ttl_seconds', 120));
    }

    protected function completedTtlHours(): int
    {
        return max(1, (int) config('literacy.submission_queue.completed_ttl_hours', 24));
    }
}
