<?php

namespace App\Support\StudentSync;

use App\Models\StudentSyncPreview;
use App\Models\StudentSyncRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

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
        return DB::transaction(function () use (
            $actorId,
            $checksum,
            $clientId,
            $idempotencyKey,
            $previewToken,
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

            /** @var StudentSyncRun|null $existing */
            $existing = StudentSyncRun::query()
                ->where('operation', 'apply')
                ->where('client_id', $clientId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $result = $existing->result_summary;

                if (
                    is_array($result)
                    && hash_equals((string) ($result['preview_token'] ?? ''), $previewToken)
                    && hash_equals(strtolower((string) $existing->payload_checksum), strtolower($checksum))
                ) {
                    return $result;
                }

                $this->rejectPreview('The idempotency key belongs to a different apply request.');
            }

            $this->validatePreviewState($preview);
            $startedAt = now();
            $snapshot = $preview->encrypted_payload;
            $snapshotItems = is_array($snapshot) ? ($snapshot['items'] ?? null) : null;

            if (! is_array($snapshotItems)) {
                $this->rejectPreview('The preview snapshot is invalid.');
            }

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
                $outcome = $this->recheckItem($item, $sharedSchema);
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
            $run = StudentSyncRun::query()->create([
                'operation' => 'apply',
                'client_id' => $clientId,
                'user_id' => $actorId,
                'status' => 'running',
                'idempotency_key' => $idempotencyKey,
                'payload_checksum' => $preview->payload_checksum,
                'started_at' => $startedAt,
            ]);
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
                'finished_at' => now(),
            ])->save();

            return $result;
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, int>  $sharedSchema
     * @return array{status: string, source_id: int, target_id: ?int, changed_fields: array<int, string>, reason: string, patch: array<string, mixed>, before: array<string, mixed>}
     */
    private function recheckItem(array $item, array $sharedSchema): array
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
        $match = $this->matcher->match(['id' => $sourceId, ...$identity]);

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

        if ($previewTargetId === null || $targetId !== $previewTargetId) {
            return [
                'status' => 'conflict',
                'source_id' => $sourceId,
                'target_id' => $targetId,
                'changed_fields' => [],
                'reason' => 'stale_target',
                'patch' => [],
                'before' => [],
            ];
        }

        $fields = is_array($item['fields'] ?? null) ? $item['fields'] : [];
        $sharedColumns = array_values(array_intersect(array_keys($fields), array_keys($sharedSchema)));
        sort($sharedColumns, SORT_STRING);
        $before = $match->matched->getAttributes();
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
