<?php

namespace App\Http\Middleware;

use App\Models\StudentSyncNonce;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyStudentSyncSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = (bool) config('student_sync.receiver.enabled', false);
        $configuredClientId = (string) config('student_sync.receiver.client_id');
        $secret = (string) config('student_sync.receiver.secret');

        if (! $enabled || $configuredClientId === '' || strlen($secret) < 32) {
            return $this->reject();
        }

        $clientId = (string) $request->header('X-Student-Sync-Client', '');
        $timestamp = (string) $request->header('X-Student-Sync-Timestamp', '');
        $nonce = (string) $request->header('X-Student-Sync-Nonce', '');
        $idempotencyKey = (string) $request->header('X-Student-Sync-Idempotency-Key', '');
        $claimedBodyHash = (string) $request->header('X-Student-Sync-Body-SHA256', '');
        $signature = (string) $request->header('X-Student-Sync-Signature', '');

        if (
            $clientId === '' || $timestamp === '' || $nonce === '' || $idempotencyKey === ''
            || $claimedBodyHash === '' || $signature === ''
            || ! hash_equals($configuredClientId, $clientId)
            || ! ctype_digit($timestamp)
        ) {
            return $this->reject();
        }

        $clockSkew = max(0, (int) config('student_sync.security.clock_skew_seconds', 300));
        $signedTimestamp = (int) $timestamp;
        $now = now();

        if (abs($now->timestamp - $signedTimestamp) > $clockSkew) {
            return $this->reject();
        }

        $bodyHash = hash('sha256', $request->getContent());

        if (! hash_equals($bodyHash, $claimedBodyHash)) {
            return $this->reject();
        }

        $canonical = implode("\n", [
            strtoupper($request->method()),
            '/'.ltrim($request->path(), '/'),
            $timestamp,
            $nonce,
            $idempotencyKey,
            $bodyHash,
        ]);
        $expectedSignature = hash_hmac('sha256', $canonical, $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            return $this->reject();
        }

        StudentSyncNonce::query()->where('expires_at', '<=', $now)->delete();

        try {
            StudentSyncNonce::create([
                'client_id' => $clientId,
                'nonce' => $nonce,
                'expires_at' => $now->copy()
                    ->setTimestamp($signedTimestamp)
                    ->addSeconds($clockSkew + 1),
            ]);
        } catch (QueryException) {
            return $this->reject();
        }

        $request->attributes->set('student_sync_client_id', $clientId);

        return $next($request);
    }

    private function reject(): JsonResponse
    {
        return response()->json(['message' => 'Unauthorized.'], 401);
    }
}
