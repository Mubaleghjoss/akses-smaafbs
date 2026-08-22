<?php

namespace App\Support\StudentSync;

use App\Models\DataSiswa;
use App\Models\StudentSyncPreview;
use App\Models\StudentSyncRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StudentSyncApplyService
{
    public function __construct(
        private readonly StudentSyncMatcher $matcher,
        private readonly StudentSyncMergePolicy $mergePolicy,
        private readonly StudentSyncBackupStore $backupStore,
    ) {}

    /** @return array<string, mixed> */
    public function apply(
        string $clientId,
        string $previewToken,
        string $checksum,
        string $idempotencyKey,
        ?int $actorId,
    ): array {
        [$run, $claimed] = $this->claimRun(
            $clientId,
            $previewToken,
            $checksum,
            $idempotencyKey,
            $actorId,
        );

        $this->validateRunBinding($run, $clientId, $previewToken, $checksum);

        if (! $claimed) {
            if ($run->status === 'completed' && is_array($run->result_summary)) {
                return $run->result_summary;
            }

            $this->rejectPreview('The idempotency key is already being processed.');
        }

        try {
            return DB::transaction(function () use (
                $checksum,
                $clientId,
                $previewToken,
                $run,
            ): array {
                /** @var StudentSyncPreview|null $preview */
                $preview = StudentSyncPreview::query()
                    ->whereKey($previewToken)
                    ->lockForUpdate()
                    ->first();

                if ($preview === null) {
                    $this->rejectPreview('The preview token is invalid.');
                }

                $this->validatePreviewIdentity($preview, $clientId, $checksum);
                $this->validatePreviewState($preview);
                $snapshot = $preview->encrypted_payload;
                $snapshotItems = is_array($snapshot) ? ($snapshot['items'] ?? null) : null;

                if (! is_array($snapshotItems)) {
                    $this->rejectPreview('The preview snapshot is invalid.');
                }

                $this->rejectDuplicateTargets($snapshotItems);
                $lockedTargets = $this->lockAllTargets();
                $sharedSchema = array_flip(Schema::getColumnListing('data_siswa'));
                $counts = [
                    'total' => count($snapshotItems),
                    'update' => 0,
                    'unchanged' => 0,
                    'conflict' => 0,
                    'not_found' => 0,
                ];
                $fieldSummary = [];
                $items = [];
                $updates = [];
                $backupRecords = [];

                foreach ($snapshotItems as $item) {
                    $outcome = $this->recheckItem($item, $sharedSchema, $lockedTargets);
                    $counts[$outcome['status']]++;

                    foreach ($outcome['changed_fields'] as $field) {
                        $fieldSummary[$field] = ($fieldSummary[$field] ?? 0) + 1;
                    }

                    $items[] = [
                        'status' => $outcome['status'],
                        'source_id' => $outcome['source_id'],
                        'target_id' => $outcome['target_id'],
                        'changed_fields' => $outcome['changed_fields'],
                        'reason' => $outcome['reason'],
                    ];

                    if ($outcome['status'] !== 'update') {
                        continue;
                    }

                    $updates[] = [
                        'target_id' => $outcome['target_id'],
                        'patch' => $outcome['patch'],
                    ];
                    $backupRecords[] = [
                        'target_id' => $outcome['target_id'],
                        'changed_fields' => $outcome['changed_fields'],
                        'before' => $outcome['before'],
                    ];
                }

                ksort($fieldSummary, SORT_STRING);
                $backupPath = null;

                if ($backupRecords !== []) {
                    $backupPath = $this->backupStore->write((string) $run->getKey(), [
                        'run_id' => $run->getKey(),
                        'client_id' => $clientId,
                        'preview_token' => $preview->getKey(),
                        'payload_checksum' => $preview->payload_checksum,
                        'created_at' => now()->toIso8601String(),
                        'records' => $backupRecords,
                    ]);
                }

                foreach ($updates as $update) {
                    DB::table('data_siswa')
                        ->where('id', $update['target_id'])
                        ->update($update['patch']);
                }

                $result = [
                    'run_id' => $run->getKey(),
                    'preview_token' => $preview->getKey(),
                    'payload_checksum' => $preview->payload_checksum,
                    'counts' => $counts,
                    'field_summary' => $fieldSummary,
                    'items' => $items,
                ];

                $preview->forceFill(['applied_at' => now()])->save();
                $run->forceFill([
                    'status' => 'completed',
                    'counts' => $counts,
                    'field_summary' => $fieldSummary,
                    'result_summary' => $result,
                    'backup_path' => $backupPath,
                    'error' => null,
                    'finished_at' => now(),
                ])->save();

                return $result;
            });
        } catch (Throwable $exception) {
            $this->recordFailureUnlessCompleted((string) $run->getKey(), $exception);

            throw $exception;
        }
    }

    /** @return array{0: StudentSyncRun, 1: bool} */
    private function claimRun(
        string $clientId,
        string $previewToken,
        string $checksum,
        string $idempotencyKey,
        ?int $actorId,
    ): array {
        $runId = (string) Str::uuid();
        $now = now();
        $binding = json_encode([
            'preview_token' => $previewToken,
        ], JSON_THROW_ON_ERROR);
        $claimed = StudentSyncRun::query()->insertOrIgnore([
            'id' => $runId,
            'operation' => 'apply',
            'client_id' => $clientId,
            'user_id' => $actorId,
            'status' => 'running',
            'idempotency_key' => $idempotencyKey,
            'payload_checksum' => strtolower($checksum),
            'result_summary' => $binding,
            'backup_path' => $this->backupStore->pathForRun($runId),
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]) === 1;

        /** @var StudentSyncRun|null $run */
        $run = StudentSyncRun::query()
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();

        if ($run === null) {
            $this->rejectPreview('The idempotency key could not be claimed.');
        }

        return [$run, $claimed && hash_equals((string) $run->getKey(), $runId)];
    }

    private function validateRunBinding(
        StudentSyncRun $run,
        string $clientId,
        string $previewToken,
        string $checksum,
    ): void {
        $result = $run->result_summary;

        if (
            $run->operation !== 'apply'
            || ! hash_equals((string) $run->client_id, $clientId)
            || ! is_array($result)
            || ! hash_equals((string) ($result['preview_token'] ?? ''), $previewToken)
            || ! hash_equals(strtolower((string) $run->payload_checksum), strtolower($checksum))
        ) {
            $this->rejectPreview('The idempotency key belongs to a different apply request.');
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshotItems
     */
    private function rejectDuplicateTargets(array $snapshotItems): void
    {
        $seen = [];

        foreach ($snapshotItems as $item) {
            if (($item['status'] ?? null) !== 'update' || ! is_numeric($item['target_id'] ?? null)) {
                continue;
            }

            $targetId = (int) $item['target_id'];

            if (isset($seen[$targetId])) {
                $this->rejectPreview('The preview contains duplicate target students.');
            }

            $seen[$targetId] = true;
        }
    }

    /**
     * Conservatively lock the complete candidate universe in deterministic order.
     * This is a rare manual operation and makes final matching a pure decision over
     * one current-read row set, including ambiguity candidates.
     *
     * @return array<int, DataSiswa>
     */
    private function lockAllTargets(): array
    {
        return DataSiswa::query()
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->mapWithKeys(fn (DataSiswa $student): array => [(int) $student->getKey() => $student])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, int>  $sharedSchema
     * @param  array<int, DataSiswa>  $lockedTargets
     * @return array{status: string, source_id: int, target_id: ?int, changed_fields: array<int, string>, reason: string, patch: array<string, mixed>, before: array<string, mixed>}
     */
    private function recheckItem(array $item, array $sharedSchema, array $lockedTargets): array
    {
        $sourceId = (int) ($item['source_id'] ?? 0);
        $previewStatus = (string) ($item['status'] ?? 'conflict');

        if ($previewStatus !== 'update') {
            return [
                'status' => in_array($previewStatus, ['unchanged', 'conflict', 'not_found'], true)
                    ? $previewStatus
                    : 'conflict',
                'source_id' => $sourceId,
                'target_id' => is_numeric($item['target_id'] ?? null) ? (int) $item['target_id'] : null,
                'changed_fields' => [],
                'reason' => (string) ($item['reason'] ?? 'preview_not_updateable'),
                'patch' => [],
                'before' => [],
            ];
        }

        $identity = is_array($item['identity'] ?? null) ? $item['identity'] : [];
        $match = $this->matcher->matchCandidates(['id' => $sourceId, ...$identity], $lockedTargets);

        if ($match->status !== StudentSyncMatchResult::MATCHED) {
            return [
                'status' => $match->status,
                'source_id' => $sourceId,
                'target_id' => null,
                'changed_fields' => [],
                'reason' => $match->reason,
                'patch' => [],
                'before' => [],
            ];
        }

        $targetId = (int) $match->matched->getKey();
        $previewTargetId = is_numeric($item['target_id'] ?? null) ? (int) $item['target_id'] : null;

        if ($previewTargetId === null || $targetId !== $previewTargetId || ! isset($lockedTargets[$targetId])) {
            return [
                'status' => 'conflict',
                'source_id' => $sourceId,
                'target_id' => $targetId,
                'changed_fields' => [],
                'reason' => $targetId !== $previewTargetId ? 'stale_target' : 'unlocked_target',
                'patch' => [],
                'before' => [],
            ];
        }

        $fields = is_array($item['fields'] ?? null) ? $item['fields'] : [];
        $sharedColumns = array_values(array_intersect(array_keys($fields), array_keys($sharedSchema)));
        sort($sharedColumns, SORT_STRING);
        $before = $lockedTargets[$targetId]->getAttributes();
        $patch = $this->mergePolicy->patch($fields, $before, $sharedColumns);
        ksort($patch, SORT_STRING);
        $changedFields = array_keys($patch);

        return [
            'status' => $patch === [] ? 'unchanged' : 'update',
            'source_id' => $sourceId,
            'target_id' => $targetId,
            'changed_fields' => $changedFields,
            'reason' => $match->reason,
            'patch' => $patch,
            'before' => $before,
        ];
    }

    private function recordFailureUnlessCompleted(string $runId, Throwable $exception): void
    {
        try {
            StudentSyncRun::query()
                ->whereKey($runId)
                ->where('status', '!=', 'completed')
                ->update([
                    'status' => 'failed',
                    'error' => $exception::class,
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);
        } catch (Throwable $auditException) {
            report($auditException);
        }
    }

    private function validatePreviewIdentity(StudentSyncPreview $preview, string $clientId, string $checksum): void
    {
        if (! hash_equals((string) $preview->client_id, $clientId)) {
            $this->rejectPreview('The preview does not belong to this client.');
        }

        if (! hash_equals(strtolower((string) $preview->payload_checksum), strtolower($checksum))) {
            $this->rejectPreview('The preview checksum does not match.');
        }
    }

    private function validatePreviewState(StudentSyncPreview $preview): void
    {
        if ($preview->expires_at->lessThanOrEqualTo(now())) {
            $this->rejectPreview('The preview has expired.');
        }

        if ($preview->applied_at !== null) {
            $this->rejectPreview('The preview has already been applied.');
        }
    }

    private function rejectPreview(string $message): never
    {
        throw ValidationException::withMessages(['preview_token' => $message]);
    }
}
