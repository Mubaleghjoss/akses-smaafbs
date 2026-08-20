<?php

namespace App\Support\StudentSync;

use App\Models\StudentSyncScopeTokenRecord;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StudentSyncScopeToken
{
    private const TTL_MINUTES = 15;

    /** @param (Closure(): void)|null $afterClaim */
    public function __construct(
        private readonly ?string $connection = null,
        private readonly ?Closure $afterClaim = null,
    ) {}

    /** @param array<int, int|string> $studentIds */
    public function issue(array $studentIds, int $userId): string
    {
        $token = Str::random(64);

        $this->query()->create([
            'user_id' => $userId,
            'token_hash' => hash('sha256', $token),
            'encrypted_student_ids' => $this->normalizeIds($studentIds),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        return $token;
    }

    /** @return array<int, int> */
    public function consume(string $token, int $userId): array
    {
        if ($token === '' || $userId < 1) {
            return [];
        }

        try {
            return $this->database()->transaction(function () use ($token, $userId): array {
                $record = $this->query()
                    ->where('token_hash', hash('sha256', $token))
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();

                if ($record === null || $record->consumed_at !== null || $record->expires_at->isPast()) {
                    return [];
                }

                $claimed = $this->query()
                    ->whereKey($record->getKey())
                    ->whereNull('consumed_at')
                    ->where('expires_at', '>', now())
                    ->update(['consumed_at' => now()]);

                if ($claimed !== 1) {
                    return [];
                }

                if ($this->afterClaim !== null) {
                    ($this->afterClaim)();
                }

                return $this->normalizeIds($record->encrypted_student_ids);
            }, 3);
        } catch (QueryException) {
            return [];
        }
    }

    private function database(): Connection
    {
        return DB::connection($this->connection);
    }

    /** @return Builder<StudentSyncScopeTokenRecord> */
    private function query(): Builder
    {
        return StudentSyncScopeTokenRecord::on($this->connection);
    }

    /**
     * @param  array<mixed>  $ids
     * @return array<int, int>
     */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0,
            $ids,
        ), static fn (int $id): bool => $id > 0)));
    }
}
