<?php

namespace App\Support\StudentSync;

use App\Models\StudentSyncPreview;
use App\Models\StudentSyncRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StudentSyncPreviewService
{
    public function __construct(
        private readonly StudentSyncMatcher $matcher,
        private readonly StudentSyncMergePolicy $mergePolicy,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $students
     * @return array<string, mixed>
     */
    public function preview(string $clientId, array $students, ?int $actorId): array
    {
        $startedAt = now();
        $payloadChecksum = self::payloadChecksum($students);
        $sharedSchema = array_flip(Schema::getColumnListing('data_siswa'));
        $items = [];
        $counts = [
            'total' => count($students),
            'update' => 0,
            'unchanged' => 0,
            'conflict' => 0,
            'not_found' => 0,
        ];
        $fieldSummary = [];

        foreach ($students as $student) {
            $sourceId = (int) $student['source_id'];
            $identity = ['id' => $sourceId, ...$student['identity']];
            $match = $this->matcher->match($identity);
            $targetId = $match->matched?->getKey();
            $patch = [];

            if ($match->status === StudentSyncMatchResult::MATCHED) {
                $fields = $student['fields'];
                $sharedColumns = array_values(array_intersect(array_keys($fields), array_keys($sharedSchema)));
                sort($sharedColumns, SORT_STRING);
                $patch = $this->mergePolicy->patch(
                    $fields,
                    $match->matched->getAttributes(),
                    $sharedColumns,
                );
                ksort($patch, SORT_STRING);
                $status = $patch === [] ? 'unchanged' : 'update';
            } else {
                $status = $match->status;
            }

            $changedFields = array_keys($patch);
            $counts[$status]++;

            foreach ($changedFields as $field) {
                $fieldSummary[$field] = ($fieldSummary[$field] ?? 0) + 1;
            }

            // Rincian nilai lama -> baru per field yang berubah (hanya field data_siswa
            // yang di-preview). "before" diambil dari record server saat ini; "after"
            // dari patch. Disimpan di snapshot terenkripsi; ditampilkan hanya ke
            // pengguna berwenang di halaman push. Tidak memuat kredensial/secret.
            $changes = [];
            if ($patch !== [] && $match->status === StudentSyncMatchResult::MATCHED) {
                $currentAttributes = $match->matched->getAttributes();
                foreach ($patch as $field => $newValue) {
                    $changes[$field] = [
                        'before' => array_key_exists($field, $currentAttributes) ? $currentAttributes[$field] : null,
                        'after' => $newValue,
                    ];
                }
            }

            $items[] = [
                'status' => $status,
                'source_id' => $sourceId,
                'target_id' => $targetId,
                'changed_fields' => $changedFields,
                'changes' => $changes,
                'reason' => $match->reason,
                'identity' => $student['identity'],
                'fields' => $student['fields'],
                'source_checksum' => $student['source_checksum'],
                'context' => $student['context'] ?? null,
                'patch' => $patch,
            ];
        }

        ksort($fieldSummary, SORT_STRING);
        $expiresAt = now()->addSeconds(max(1, (int) config('student_sync.security.preview_ttl_seconds', 900)));

        /** @var StudentSyncPreview $preview */
        $preview = DB::transaction(function () use (
            $actorId,
            $clientId,
            $counts,
            $expiresAt,
            $fieldSummary,
            $items,
            $payloadChecksum,
            $startedAt,
        ): StudentSyncPreview {
            $preview = StudentSyncPreview::query()->create([
                'client_id' => $clientId,
                'payload_checksum' => $payloadChecksum,
                'encrypted_payload' => [
                    'payload_checksum' => $payloadChecksum,
                    'counts' => $counts,
                    'field_summary' => $fieldSummary,
                    'items' => $items,
                ],
                'expires_at' => $expiresAt,
            ]);

            StudentSyncRun::query()->create([
                'operation' => 'preview',
                'client_id' => $clientId,
                'user_id' => $actorId,
                'status' => 'completed',
                'payload_checksum' => $payloadChecksum,
                'counts' => $counts,
                'field_summary' => $fieldSummary,
                'result_summary' => ['preview_token' => $preview->getKey()],
                'started_at' => $startedAt,
                'finished_at' => now(),
            ]);

            return $preview;
        });

        return [
            'preview_token' => $preview->getKey(),
            'payload_checksum' => $payloadChecksum,
            'expires_at' => $expiresAt->toIso8601String(),
            'counts' => $counts,
            'field_summary' => $fieldSummary,
            'items' => array_map(static fn (array $item): array => [
                'status' => $item['status'],
                'source_id' => $item['source_id'],
                'target_id' => $item['target_id'],
                'changed_fields' => $item['changed_fields'],
                'changes' => $item['changes'],
                'reason' => $item['reason'],
            ], $items),
        ];
    }

    /** @param array<int, array<string, mixed>> $students */
    public static function payloadChecksum(array $students): string
    {
        return hash('sha256', json_encode(
            self::canonicalize($students),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }

        return $value;
    }
}
