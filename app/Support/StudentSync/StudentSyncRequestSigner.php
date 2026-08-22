<?php

namespace App\Support\StudentSync;

use Illuminate\Support\Str;
use RuntimeException;

class StudentSyncRequestSigner
{
    /**
     * @return array<string, string>
     */
    public function headers(string $method, string $path, string $body, string $idempotencyKey): array
    {
        $clientId = (string) config('student_sync.client.client_id');
        $secret = (string) config('student_sync.client.secret');

        if ($clientId === '' || strlen($secret) < 32) {
            throw new RuntimeException('Student sync client credentials are not configured securely.');
        }

        $timestamp = (string) now()->timestamp;
        $nonce = Str::random(40);
        $bodyHash = hash('sha256', $body);
        $canonical = implode("\n", [
            strtoupper($method),
            $path,
            $timestamp,
            $nonce,
            $idempotencyKey,
            $bodyHash,
        ]);

        return [
            'X-Student-Sync-Client' => $clientId,
            'X-Student-Sync-Timestamp' => $timestamp,
            'X-Student-Sync-Nonce' => $nonce,
            'X-Student-Sync-Idempotency-Key' => $idempotencyKey,
            'X-Student-Sync-Body-SHA256' => $bodyHash,
            'X-Student-Sync-Signature' => hash_hmac('sha256', $canonical, $secret),
        ];
    }
}
