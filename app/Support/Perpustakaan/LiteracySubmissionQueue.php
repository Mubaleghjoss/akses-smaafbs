<?php

namespace App\Support\Perpustakaan;

use App\Exceptions\LiteracySubmissionQueueBusy;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiResponse;
use App\Models\PerpustakaanLiterasiSubmissionQueueState;
use App\Models\PerpustakaanLiterasiSubmissionTicket;
use Illuminate\Http\Request;
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

        return DB::transaction(function () use ($request, $token): array {
            $state = $this->lockState();
            $this->markSubmissionActivity($state);
            $this->expireStaleTickets();
            $this->promoteWaitingTickets();

            $ticket = PerpustakaanLiterasiSubmissionTicket::query()
                ->where('public_token', $token)
                ->where('owner_hash', $this->ownerHash($request))
                ->firstOrFail();

            return $this->payload($ticket, $state);
        });
    }

    public function cancel(Request $request, string $token): array
    {
        if (! $this->enabled()) {
            return $this->disabledPayload();
        }

        return DB::transaction(function () use ($request, $token): array {
            $state = $this->lockState();
            $this->markSubmissionActivity($state);
            $this->expireStaleTickets();

            $ticket = PerpustakaanLiterasiSubmissionTicket::query()
                ->where('public_token', $token)
                ->where('owner_hash', $this->ownerHash($request))
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

            $this->promoteWaitingTickets();

            return $this->payload($ticket->refresh(), $state);
        });
    }

    public function complete(
        ?PerpustakaanLiterasiSubmissionTicket $ticket,
        ?PerpustakaanLiterasiResponse $response = null,
    ): void {
        if (! $ticket || ! $this->enabled()) {
            return;
        }

        DB::transaction(function () use ($ticket, $response): void {
            $state = $this->lockState();
            $this->markSubmissionActivity($state);
            $current = PerpustakaanLiterasiSubmissionTicket::query()
                ->whereKey($ticket->getKey())
                ->lockForUpdate()
                ->first();

            if (! $current || $current->status === PerpustakaanLiterasiSubmissionTicket::STATUS_COMPLETED) {
                return;
            }

            $durationMs = max(1, (int) optional($current->started_at)->diffInMilliseconds(now()));
            $averageMs = (int) round(($state->average_duration_ms * 0.8) + ($durationMs * 0.2));

            $state->forceFill([
                'average_duration_ms' => min(30000, max(250, $averageMs)),
            ])->save();

            $current->forceFill([
                'status' => PerpustakaanLiterasiSubmissionTicket::STATUS_COMPLETED,
                'completed_at' => now(),
                'expires_at' => now()->addHours($this->completedTtlHours()),
                'result_response_id' => $response?->getKey(),
            ])->save();

            $this->promoteWaitingTickets();
        });
    }

    public function release(?PerpustakaanLiterasiSubmissionTicket $ticket): void
    {
        if (! $ticket || ! $this->enabled()) {
            return;
        }

        DB::transaction(function () use ($ticket): void {
            $state = $this->lockState();
            $this->markSubmissionActivity($state);

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

            $this->promoteWaitingTickets();
        });
    }

    public function payloadFor(?PerpustakaanLiterasiSubmissionTicket $ticket): array
    {
        if (! $ticket || ! $this->enabled()) {
            return $this->disabledPayload();
        }

        return DB::transaction(function () use ($ticket): array {
            $state = $this->lockState();
            $this->markSubmissionActivity($state);
            $this->expireStaleTickets();
            $this->promoteWaitingTickets();

            return $this->payload($ticket->fresh(), $state);
        });
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

        return DB::transaction(function () use ($request, $operation, $operationKey, $materialId, $responseId, $studentId): PerpustakaanLiterasiSubmissionTicket {
            $state = $this->lockState();
            $this->markSubmissionActivity($state);
            $this->expireStaleTickets();

            $ownerHash = $this->ownerHash($request);
            $ticket = PerpustakaanLiterasiSubmissionTicket::query()
                ->where('owner_hash', $ownerHash)
                ->where('operation_key', $operationKey)
                ->whereIn('status', [
                    PerpustakaanLiterasiSubmissionTicket::STATUS_WAITING,
                    PerpustakaanLiterasiSubmissionTicket::STATUS_ADMITTED,
                    PerpustakaanLiterasiSubmissionTicket::STATUS_PROCESSING,
                    PerpustakaanLiterasiSubmissionTicket::STATUS_COMPLETED,
                ])
                ->latest('id')
                ->first();

            if (! $ticket) {
                $ticket = PerpustakaanLiterasiSubmissionTicket::query()->create([
                    'public_token' => Str::random(64),
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
                ]);
            }

            $this->promoteWaitingTickets();

            return $ticket->refresh();
        });
    }

    protected function claim(
        ?PerpustakaanLiterasiSubmissionTicket $ticket,
        string $expectedOperationKey,
    ): ?PerpustakaanLiterasiSubmissionTicket {
        if (! $ticket || ! $this->enabled()) {
            return null;
        }

        return DB::transaction(function () use ($ticket, $expectedOperationKey): PerpustakaanLiterasiSubmissionTicket {
            $state = $this->lockState();
            $this->markSubmissionActivity($state);
            $this->expireStaleTickets();
            $this->promoteWaitingTickets();

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
                throw new LiteracySubmissionQueueBusy($this->payload($current, $state));
            }

            $current->forceFill([
                'status' => PerpustakaanLiterasiSubmissionTicket::STATUS_PROCESSING,
                'started_at' => now(),
                'expires_at' => now()->addSeconds($this->processingTtlSeconds()),
            ])->save();

            return $current;
        });
    }

    protected function findOwnedTicket(Request $request, string $token): ?PerpustakaanLiterasiSubmissionTicket
    {
        if (! $this->enabled()) {
            abort(404);
        }

        return PerpustakaanLiterasiSubmissionTicket::query()
            ->where('public_token', $token)
            ->where('owner_hash', $this->ownerHash($request))
            ->first();
    }

    protected function lockState(): PerpustakaanLiterasiSubmissionQueueState
    {
        DB::table('perpustakaan_literasi_submission_queue_states')->insertOrIgnore([
            'scope' => self::SCOPE,
            'average_duration_ms' => 2000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return PerpustakaanLiterasiSubmissionQueueState::query()
            ->whereKey(self::SCOPE)
            ->lockForUpdate()
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

    protected function markSubmissionActivity(PerpustakaanLiterasiSubmissionQueueState $state): void
    {
        if (! $this->activityColumnAvailable()) {
            return;
        }

        $state->forceFill([
            'last_submission_activity_at' => now(),
        ])->save();
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

        return [
            'ticket' => $ticket->public_token,
            'status' => $ticket->status,
            'position' => $position,
            'estimated_wait_seconds' => $estimatedSeconds,
            'retry_after_seconds' => $retryAfterSeconds,
            'expires_at' => $ticket->expires_at?->toIso8601String(),
            'status_url' => route('library.literacy.queue.status', $ticket->public_token),
            'cancel_url' => route('library.literacy.queue.cancel', $ticket->public_token),
        ];
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
        ];
    }

    protected function ownerHash(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
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
