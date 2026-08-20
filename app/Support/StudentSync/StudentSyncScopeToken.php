<?php

namespace App\Support\StudentSync;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class StudentSyncScopeToken
{
    private const TTL_MINUTES = 15;

    /** @param array<int, int|string> $studentIds */
    public function issue(array $studentIds, int $userId): string
    {
        $token = Str::random(64);

        Cache::put($this->cacheKey($token, $userId), [
            'user_id' => $userId,
            'student_ids' => $this->normalizeIds($studentIds),
        ], now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    /** @return array<int, int> */
    public function consume(string $token, int $userId): array
    {
        $cacheKey = $this->cacheKey($token, $userId);

        try {
            return Cache::lock($cacheKey.':consume-lock', 5)->block(2, function () use ($cacheKey, $userId): array {
                $payload = Cache::get($cacheKey);

                if (! is_array($payload)
                    || ! is_int($payload['user_id'] ?? null)
                    || $payload['user_id'] !== $userId
                    || ! is_array($payload['student_ids'] ?? null)) {
                    return [];
                }

                Cache::forget($cacheKey);

                return $this->normalizeIds($payload['student_ids']);
            });
        } catch (LockTimeoutException) {
            return [];
        }
    }

    /** @param array<mixed> $ids
     * @return array<int, int>
     */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0,
            $ids,
        ), static fn (int $id): bool => $id > 0)));
    }

    private function cacheKey(string $token, int $userId): string
    {
        return 'student-sync:shortcut-scope:'.$userId.':'.hash('sha256', $token);
    }
}
