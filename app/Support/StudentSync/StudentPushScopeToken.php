<?php

namespace App\Support\StudentSync;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Throwable;

final class StudentPushScopeToken
{
    /** @param array<int, int|string> $studentIds */
    public function forUser(User $user, array $studentIds, mixed $expiresAt = null): string
    {
        $ids = $this->normalizeIds($studentIds);
        $expiresAt ??= now()->addMinutes(15);

        $encrypted = Crypt::encryptString(json_encode([
            'purpose' => 'student-server-push-scope',
            'user_id' => (int) $user->getAuthIdentifier(),
            'student_ids' => $ids,
            'expires_at' => $expiresAt->getTimestamp(),
        ], JSON_THROW_ON_ERROR));

        return rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');
    }

    /** @return array<int, int> */
    public function idsFor(?User $user, ?string $token): array
    {
        if (! $user instanceof User || ! is_string($token) || $token === '') {
            return [];
        }

        try {
            $base64 = strtr($token, '-_', '+/');
            $encrypted = base64_decode(str_pad($base64, strlen($base64) + ((4 - strlen($base64) % 4) % 4), '='), true);

            if ($encrypted === false) {
                return [];
            }

            $payload = json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($payload)
                || ($payload['purpose'] ?? null) !== 'student-server-push-scope'
                || ! is_int($payload['user_id'] ?? null)
                || ! hash_equals((string) $user->getAuthIdentifier(), (string) $payload['user_id'])
                || ! is_int($payload['expires_at'] ?? null)
                || $payload['expires_at'] <= now()->getTimestamp()
                || ! is_array($payload['student_ids'] ?? null)) {
                return [];
            }

            return $this->normalizeIds($payload['student_ids']);
        } catch (Throwable) {
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
}
