<?php

namespace App\Support\Auth\WebAuthn;

use App\Contracts\Auth\WebAuthnChallengeFlow;
use App\Contracts\Auth\WebAuthnCredentialDomain;
use App\Models\User;
use App\Models\WebAuthnChallenge;
use App\Models\WebAuthnCredential;
use Illuminate\Support\Str;

class DatabaseWebAuthnChallengeFlow implements WebAuthnChallengeFlow
{
    public function __construct(
        private readonly WebAuthnCredentialDomain $credentials,
    ) {}

    public function issueAssertionChallenge(User $user, bool $browserSupported, array $context = []): WebAuthnChallengeIssueResult
    {
        $challenge = $browserSupported ? $this->generateChallenge() : null;

        $record = WebAuthnChallenge::query()->create([
            'challenge_id' => (string) Str::uuid(),
            'user_id' => $user->getKey(),
            'ceremony' => 'assertion',
            'challenge_hash' => $challenge ? $this->hashChallenge($challenge) : null,
            'challenge_expires_at' => now()->addMinutes(5),
            'browser_supported' => $browserSupported,
            'context' => $context,
            'cancelled_at' => $browserSupported ? null : now(),
            'failure_reason' => $browserSupported ? null : WebAuthnChallengeIssueResult::UNSUPPORTED_BROWSER,
        ]);

        if (! $browserSupported) {
            return new WebAuthnChallengeIssueResult(
                status: WebAuthnChallengeIssueResult::UNSUPPORTED_BROWSER,
                challengeId: $record->challenge_id,
                challenge: null,
                canFallbackToPassword: true,
            );
        }

        return new WebAuthnChallengeIssueResult(
            status: WebAuthnChallengeIssueResult::ISSUED,
            challengeId: $record->challenge_id,
            challenge: $challenge,
            canFallbackToPassword: false,
        );
    }

    public function cancel(string $challengeId, string $reason = 'ceremony_cancelled'): void
    {
        $record = WebAuthnChallenge::query()
            ->where('challenge_id', $challengeId)
            ->first();

        if (! $record || $record->cancelled_at !== null) {
            return;
        }

        $record->forceFill([
            'cancelled_at' => now(),
            'failure_reason' => trim($reason) !== '' ? trim($reason) : WebAuthnAssertionResult::CEREMONY_CANCELLED,
        ])->save();
    }

    public function verifyAssertion(
        string $challengeId,
        string $clientChallenge,
        string $credentialId,
        array $payload = [],
    ): WebAuthnAssertionResult {
        $record = WebAuthnChallenge::query()
            ->where('challenge_id', $challengeId)
            ->first();

        if (! $record) {
            return new WebAuthnAssertionResult(WebAuthnAssertionResult::INVALID_CHALLENGE, true);
        }

        if (! $record->browser_supported) {
            return $this->markChallengeFailure($record, WebAuthnAssertionResult::UNSUPPORTED_BROWSER);
        }

        if ($record->isCancelled()) {
            return $this->markChallengeFailure($record, WebAuthnAssertionResult::CEREMONY_CANCELLED);
        }

        if ($record->used_at !== null || $record->challenge_expires_at->isPast()) {
            return $this->markChallengeFailure($record, WebAuthnAssertionResult::INVALID_CHALLENGE);
        }

        if ($record->challenge_hash !== $this->hashChallenge($clientChallenge)) {
            return $this->markChallengeFailure($record, WebAuthnAssertionResult::INVALID_CHALLENGE);
        }

        $activeCredential = $this->credentials->findActiveByCredentialId($credentialId);

        if (! $activeCredential) {
            $revokedCredential = $this->findRevokedCredential($credentialId);

            if ($revokedCredential) {
                return $this->markChallengeFailure($record, WebAuthnAssertionResult::CREDENTIAL_REVOKED);
            }

            return $this->markChallengeFailure($record, WebAuthnAssertionResult::CREDENTIAL_NOT_FOUND);
        }

        if ((int) $activeCredential->user_id !== (int) $record->user_id) {
            return $this->markChallengeFailure($record, WebAuthnAssertionResult::CREDENTIAL_NOT_FOUND);
        }

        $assertion = $this->extractAndValidateAssertionPayload(
            payload: $payload,
            credentialId: $credentialId,
            clientChallenge: $clientChallenge,
            publicKey: (string) $activeCredential->public_key,
        );

        if (! $assertion['valid']) {
            return $this->markChallengeFailure($record, WebAuthnAssertionResult::INVALID_CHALLENGE);
        }

        $signCount = $assertion['sign_count'];

        if ($signCount !== null && $signCount < (int) $activeCredential->sign_count) {
            return $this->markChallengeFailure($record, WebAuthnAssertionResult::SIGN_COUNT_REGRESSION);
        }

        $record->forceFill([
            'used_at' => now(),
            'failure_reason' => null,
        ])->save();

        $activeCredential->forceFill([
            'last_used_at' => now(),
            'sign_count' => $signCount ?? $activeCredential->sign_count,
        ])->save();

        return new WebAuthnAssertionResult(WebAuthnAssertionResult::VERIFIED, false);
    }

    private function markChallengeFailure(WebAuthnChallenge $record, string $reason): WebAuthnAssertionResult
    {
        $record->forceFill([
            'failure_reason' => $reason,
        ])->save();

        return new WebAuthnAssertionResult($reason, true);
    }

    private function findRevokedCredential(string $credentialId): ?WebAuthnCredential
    {
        return WebAuthnCredential::query()
            ->where('credential_id', $credentialId)
            ->whereNotNull('revoked_at')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{valid: bool, sign_count: int|null}
     */
    private function extractAndValidateAssertionPayload(
        array $payload,
        string $credentialId,
        string $clientChallenge,
        string $publicKey,
    ): array {
        $clientDataJsonEncoded = trim((string) ($payload['client_data_json'] ?? ''));
        $authenticatorDataEncoded = trim((string) ($payload['authenticator_data'] ?? ''));
        $signatureEncoded = trim((string) ($payload['signature'] ?? ''));
        $payloadCredentialId = trim((string) ($payload['credential_id'] ?? ''));
        $rawIdEncoded = trim((string) ($payload['raw_id'] ?? ''));

        if (
            $clientDataJsonEncoded === '' ||
            $authenticatorDataEncoded === '' ||
            $signatureEncoded === '' ||
            $payloadCredentialId === '' ||
            $rawIdEncoded === ''
        ) {
            return ['valid' => false, 'sign_count' => null];
        }

        if (! hash_equals($credentialId, $payloadCredentialId)) {
            return ['valid' => false, 'sign_count' => null];
        }

        if (! hash_equals($credentialId, $rawIdEncoded)) {
            return ['valid' => false, 'sign_count' => null];
        }

        $clientDataJson = $this->decodeBase64Url($clientDataJsonEncoded);
        $authenticatorData = $this->decodeBase64Url($authenticatorDataEncoded);
        $signature = $this->decodeBase64Url($signatureEncoded);

        if ($clientDataJson === null || $authenticatorData === null || $signature === null) {
            return ['valid' => false, 'sign_count' => null];
        }

        if (strlen($signature) === 0 || strlen($authenticatorData) < 37) {
            return ['valid' => false, 'sign_count' => null];
        }

        $clientData = json_decode($clientDataJson, true);

        if (! is_array($clientData)) {
            return ['valid' => false, 'sign_count' => null];
        }

        $type = trim((string) ($clientData['type'] ?? ''));
        $challengeFromClientData = trim((string) ($clientData['challenge'] ?? ''));
        $origin = trim((string) ($clientData['origin'] ?? ''));

        if ($type !== 'webauthn.get' || $challengeFromClientData === '' || $origin === '') {
            return ['valid' => false, 'sign_count' => null];
        }

        if (! hash_equals($challengeFromClientData, $clientChallenge)) {
            return ['valid' => false, 'sign_count' => null];
        }

        $rpId = $this->resolveRpId();

        if ($rpId === null || ! $this->originMatchesRpId($origin, $rpId)) {
            return ['valid' => false, 'sign_count' => null];
        }

        $rpIdHash = substr($authenticatorData, 0, 32);

        if (! hash_equals($rpIdHash, hash('sha256', $rpId, true))) {
            return ['valid' => false, 'sign_count' => null];
        }

        if (! $this->verifyAssertionSignature($authenticatorData, $clientDataJson, $signature, $publicKey)) {
            return ['valid' => false, 'sign_count' => null];
        }

        $signCount = unpack('N', substr($authenticatorData, 33, 4));

        return [
            'valid' => is_array($signCount) && array_key_exists(1, $signCount),
            'sign_count' => is_array($signCount) && array_key_exists(1, $signCount) ? (int) $signCount[1] : null,
        ];
    }

    private function resolveRpId(): ?string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($host) || trim($host) === '') {
            $host = parse_url((string) request()?->root(), PHP_URL_HOST);
        }

        $host = is_string($host) ? trim(strtolower($host)) : '';

        return $host !== '' ? $host : null;
    }

    private function originMatchesRpId(string $origin, string $rpId): bool
    {
        $originHost = parse_url($origin, PHP_URL_HOST);

        if (! is_string($originHost) || trim($originHost) === '') {
            return false;
        }

        return trim(strtolower($originHost)) === trim(strtolower($rpId));
    }

    private function decodeBase64Url(string $value): ?string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace(['-', '_'], ['+', '/'], $normalized);
        $padding = strlen($normalized) % 4;

        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);

        return is_string($decoded) ? $decoded : null;
    }

    private function verifyAssertionSignature(
        string $authenticatorData,
        string $clientDataJson,
        string $signature,
        string $storedPublicKey,
    ): bool {
        $publicKeyPem = $this->normalizePublicKey($storedPublicKey);

        if ($publicKeyPem === null) {
            return false;
        }

        $signedData = $authenticatorData.hash('sha256', $clientDataJson, true);
        $verified = openssl_verify($signedData, $signature, $publicKeyPem, OPENSSL_ALGO_SHA256);

        return $verified === 1;
    }

    private function normalizePublicKey(string $storedPublicKey): ?string
    {
        $trimmed = trim($storedPublicKey);

        if ($trimmed === '') {
            return null;
        }

        if (str_contains($trimmed, 'BEGIN PUBLIC KEY')) {
            return $trimmed;
        }

        $der = $this->decodeBase64Url($trimmed);

        if ($der === null) {
            return null;
        }

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($der), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    private function generateChallenge(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function hashChallenge(string $challenge): string
    {
        return hash('sha256', trim($challenge));
    }
}
