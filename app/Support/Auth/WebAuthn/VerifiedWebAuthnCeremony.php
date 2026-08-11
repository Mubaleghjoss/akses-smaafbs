<?php

namespace App\Support\Auth\WebAuthn;

use App\Models\User;
use App\Models\WebAuthnChallenge;
use App\Models\WebAuthnCredential;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use Throwable;

class VerifiedWebAuthnCeremony
{
    public function issueRegistration(User $user, bool $browserSupported, array $context = []): WebAuthnChallengeIssueResult
    {
        if (! config('webauthn.enabled')) {
            return $this->unavailable(WebAuthnChallengeIssueResult::DISABLED);
        }

        if (! $browserSupported) {
            return $this->unavailable(WebAuthnChallengeIssueResult::UNSUPPORTED_BROWSER);
        }

        $activeCount = WebAuthnCredential::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->whereNotNull('credential_public_key')
            ->whereNotNull('verified_at')
            ->count();

        if ($activeCount >= (int) config('webauthn.max_credentials_per_user', 5)) {
            throw new WebAuthnCeremonyException('credential_limit', 'Maksimal lima perangkat passkey aktif per akun. Nonaktifkan perangkat lama terlebih dahulu.');
        }

        $webauthn = $this->makeWebAuthn();
        $excludeCredentials = WebAuthnCredential::query()
            ->where('user_id', $user->getKey())
            ->pluck('credential_id')
            ->map(function (string $credentialId): ?ByteBuffer {
                try {
                    return ByteBuffer::fromBase64Url($credentialId);
                } catch (Throwable) {
                    return null;
                }
            })
            ->filter()
            ->values()
            ->all();

        $options = $webauthn->getCreateArgs(
            userId: $this->userHandleBinary($user),
            userName: (string) $user->username,
            userDisplayName: (string) ($user->name ?: $user->username),
            timeout: (int) config('webauthn.timeout_seconds', 60),
            requireResidentKey: 'required',
            requireUserVerification: 'required',
            crossPlatformAttachment: null,
            excludeCredentialIds: $excludeCredentials,
        );

        return $this->storeIssuedChallenge(
            ceremony: 'registration',
            user: $user,
            webauthn: $webauthn,
            publicKeyOptions: $this->normalizeOptions($options->publicKey),
            context: $context,
            canFallbackToPassword: true,
        );
    }

    public function issueDiscoverableAssertion(bool $browserSupported, array $context = []): WebAuthnChallengeIssueResult
    {
        if (! config('webauthn.enabled')) {
            return $this->unavailable(WebAuthnChallengeIssueResult::DISABLED);
        }

        if (! $browserSupported) {
            return $this->unavailable(WebAuthnChallengeIssueResult::UNSUPPORTED_BROWSER);
        }

        $webauthn = $this->makeWebAuthn();
        $options = $webauthn->getGetArgs(
            credentialIds: [],
            timeout: (int) config('webauthn.timeout_seconds', 60),
            allowUsb: true,
            allowNfc: true,
            allowBle: true,
            allowHybrid: true,
            allowInternal: true,
            requireUserVerification: 'required',
        );

        return $this->storeIssuedChallenge(
            ceremony: 'assertion_discoverable',
            user: null,
            webauthn: $webauthn,
            publicKeyOptions: $this->normalizeOptions($options->publicKey),
            context: $context,
            canFallbackToPassword: true,
        );
    }

    public function verifyRegistration(User $user, string $challengeId, array $payload, ?string $deviceName = null): WebAuthnCredential
    {
        try {
            return DB::transaction(function () use ($user, $challengeId, $payload, $deviceName): WebAuthnCredential {
                $challenge = $this->lockChallenge($challengeId, 'registration', $user);
                $clientData = $this->clientData($payload, 'webauthn.create');
                $this->assertChallengeMatches($challenge, $clientData['challenge']);

                $attestationObject = $this->decodeRequired($payload, 'attestation_object');
                $webauthn = $this->makeWebAuthn();
                $registration = $webauthn->processCreate(
                    clientDataJSON: $clientData['binary'],
                    attestationObject: $attestationObject,
                    challenge: ByteBuffer::fromBase64Url($clientData['challenge']),
                    requireUserVerification: true,
                    requireUserPresent: true,
                    failIfRootMismatch: false,
                    requireCtsProfileMatch: true,
                );

                $credentialId = $this->normalizeCredentialId($registration->credentialId ?? null);
                $credential = WebAuthnCredential::query()
                    ->where('credential_id', $credentialId)
                    ->lockForUpdate()
                    ->first();

                if ($credential && (int) $credential->user_id !== (int) $user->getKey()) {
                    throw new WebAuthnCeremonyException('credential_conflict', 'Passkey ini sudah terhubung ke akun lain.');
                }

                $activeCount = WebAuthnCredential::query()
                    ->where('user_id', $user->getKey())
                    ->whereNull('revoked_at')
                    ->whereNotNull('credential_public_key')
                    ->whereNotNull('verified_at')
                    ->when($credential, fn ($query) => $query->where('id', '!=', $credential->getKey()))
                    ->count();

                if ($activeCount >= (int) config('webauthn.max_credentials_per_user', 5)) {
                    throw new WebAuthnCeremonyException('credential_limit', 'Maksimal lima perangkat passkey aktif per akun.');
                }

                $credential ??= new WebAuthnCredential;
                $publicKey = trim((string) ($registration->credentialPublicKey ?? ''));
                $counter = max(0, (int) ($registration->signatureCounter ?? 0));
                $name = $this->deviceName($deviceName);

                $credential->forceFill([
                    'user_id' => $user->getKey(),
                    'credential_id' => $credentialId,
                    'public_key' => $publicKey,
                    'credential_public_key' => $publicKey,
                    'sign_count' => $counter,
                    'signature_counter' => $counter,
                    'attestation_format' => $this->nullableString($registration->attestationFormat ?? null),
                    'aaguid' => $this->normalizeAaguid($registration->AAGUID ?? null),
                    'transports' => $this->transports($payload['transports'] ?? []),
                    'user_handle' => $this->userHandleEncoded($user),
                    'user_verified' => (bool) ($registration->userVerified ?? false),
                    'backup_eligible' => (bool) ($registration->isBackupEligible ?? false),
                    'backed_up' => (bool) ($registration->isBackedUp ?? false),
                    'label' => $name,
                    'device_name' => $name,
                    'verified_at' => now(),
                    'last_used_at' => null,
                    'revoked_at' => null,
                ])->save();

                $challenge->forceFill(['used_at' => now(), 'failure_reason' => null])->save();

                Log::info('WebAuthn registration verified.', [
                    'user_id' => $user->getKey(),
                    'challenge_ref' => substr(hash('sha256', $challengeId), 0, 12),
                    'attestation_format' => $credential->attestation_format,
                ]);

                return $credential->fresh();
            }, 3);
        } catch (WebAuthnCeremonyException $exception) {
            $this->markFailure($challengeId, $exception->status);
            $this->logFailure('registration', $challengeId, $exception->status);

            throw $exception;
        } catch (WebAuthnException $exception) {
            $mapped = $this->mapLibraryException($exception);
            $this->markFailure($challengeId, $mapped->status);
            $this->logFailure('registration', $challengeId, $mapped->status);

            throw $mapped;
        }
    }

    public function verifyDiscoverableAssertion(string $challengeId, string $credentialId, array $payload): WebAuthnAssertionResult
    {
        try {
            return DB::transaction(function () use ($challengeId, $credentialId, $payload): WebAuthnAssertionResult {
                $challenge = $this->lockChallenge($challengeId, 'assertion_discoverable');
                $clientData = $this->clientData($payload, 'webauthn.get');
                $this->assertChallengeMatches($challenge, $clientData['challenge']);

                $credential = WebAuthnCredential::query()
                    ->where('credential_id', trim($credentialId))
                    ->lockForUpdate()
                    ->first();

                if (! $credential) {
                    throw new WebAuthnCeremonyException(WebAuthnAssertionResult::CREDENTIAL_NOT_FOUND, 'Passkey tidak dikenali.');
                }

                if ($credential->revoked_at !== null) {
                    throw new WebAuthnCeremonyException(WebAuthnAssertionResult::CREDENTIAL_REVOKED, 'Passkey sudah dinonaktifkan.');
                }

                if (! $credential->isVerifiedPasskey()) {
                    throw new WebAuthnCeremonyException('legacy_credential', 'Passkey format lama harus didaftarkan ulang.');
                }

                $user = User::query()->find($credential->user_id);

                if (! $user) {
                    throw new WebAuthnCeremonyException(WebAuthnAssertionResult::CREDENTIAL_NOT_FOUND, 'Akun passkey tidak ditemukan.');
                }

                $userHandle = trim((string) ($payload['user_handle'] ?? ''));

                if ($userHandle === '' || blank($credential->user_handle) || ! hash_equals((string) $credential->user_handle, $userHandle)) {
                    throw new WebAuthnCeremonyException(WebAuthnAssertionResult::CREDENTIAL_NOT_FOUND, 'Passkey tidak cocok dengan akun.');
                }

                $webauthn = $this->makeWebAuthn();
                $webauthn->processGet(
                    clientDataJSON: $clientData['binary'],
                    authenticatorData: $this->decodeRequired($payload, 'authenticator_data'),
                    signature: $this->decodeRequired($payload, 'signature'),
                    credentialPublicKey: (string) $credential->credential_public_key,
                    challenge: ByteBuffer::fromBase64Url($clientData['challenge']),
                    prevSignatureCnt: $credential->signature_counter,
                    requireUserVerification: true,
                    requireUserPresent: true,
                );

                $newCounter = $webauthn->getSignatureCounter();
                $credential->forceFill([
                    'signature_counter' => $newCounter ?? $credential->signature_counter,
                    'sign_count' => $newCounter ?? $credential->sign_count,
                    'last_used_at' => now(),
                ])->save();
                $challenge->forceFill(['used_at' => now(), 'user_id' => $user->getKey(), 'failure_reason' => null])->save();

                Log::info('WebAuthn assertion verified.', [
                    'user_id' => $user->getKey(),
                    'challenge_ref' => substr(hash('sha256', $challengeId), 0, 12),
                ]);

                return new WebAuthnAssertionResult(
                    WebAuthnAssertionResult::VERIFIED,
                    false,
                    $user,
                    $credential->fresh(),
                );
            }, 3);
        } catch (WebAuthnCeremonyException $exception) {
            $this->markFailure($challengeId, $exception->status);
            $this->logFailure('assertion', $challengeId, $exception->status);

            return new WebAuthnAssertionResult($exception->status, true);
        } catch (WebAuthnException $exception) {
            $mapped = $this->mapLibraryException($exception);
            $this->markFailure($challengeId, $mapped->status);
            $this->logFailure('assertion', $challengeId, $mapped->status);

            return new WebAuthnAssertionResult($mapped->status, true);
        }
    }

    private function storeIssuedChallenge(string $ceremony, ?User $user, WebAuthn $webauthn, array $publicKeyOptions, array $context, bool $canFallbackToPassword): WebAuthnChallengeIssueResult
    {
        $encodedChallenge = $webauthn->getChallenge()->jsonSerialize();
        $record = WebAuthnChallenge::query()->create([
            'challenge_id' => (string) Str::uuid(),
            'user_id' => $user?->getKey(),
            'ceremony' => $ceremony,
            'challenge_hash' => hash('sha256', $encodedChallenge),
            'challenge_expires_at' => now()->addMinutes((int) config('webauthn.challenge_ttl_minutes', 5)),
            'browser_supported' => true,
            'context' => array_merge($context, [
                'rp_id' => (string) config('webauthn.rp_id'),
                'origin' => (string) config('webauthn.origin'),
                'session_hash' => hash('sha256', (string) session()->getId()),
            ]),
        ]);

        return new WebAuthnChallengeIssueResult(
            WebAuthnChallengeIssueResult::ISSUED,
            $record->challenge_id,
            $encodedChallenge,
            $canFallbackToPassword,
            $publicKeyOptions,
        );
    }

    private function unavailable(string $status): WebAuthnChallengeIssueResult
    {
        return new WebAuthnChallengeIssueResult($status, '', null, true, null);
    }

    private function lockChallenge(string $challengeId, string $ceremony, ?User $user = null): WebAuthnChallenge
    {
        $record = WebAuthnChallenge::query()
            ->where('challenge_id', trim($challengeId))
            ->lockForUpdate()
            ->first();

        if (! $record || $record->ceremony !== $ceremony) {
            throw new WebAuthnCeremonyException(WebAuthnAssertionResult::INVALID_CHALLENGE, 'Sesi passkey tidak valid.');
        }

        if ($user && (int) $record->user_id !== (int) $user->getKey()) {
            throw new WebAuthnCeremonyException(WebAuthnAssertionResult::INVALID_CHALLENGE, 'Sesi passkey bukan milik akun ini.');
        }

        if ($record->used_at || $record->cancelled_at || $record->challenge_expires_at->isPast()) {
            throw new WebAuthnCeremonyException(WebAuthnAssertionResult::INVALID_CHALLENGE, 'Sesi passkey sudah digunakan atau kedaluwarsa.');
        }

        $sessionHash = (string) data_get($record->context, 'session_hash');

        if ($sessionHash !== '' && ! hash_equals($sessionHash, hash('sha256', (string) session()->getId()))) {
            throw new WebAuthnCeremonyException(WebAuthnAssertionResult::INVALID_CHALLENGE, 'Sesi browser passkey tidak cocok.');
        }

        return $record;
    }

    /** @return array{binary: string, challenge: string} */
    private function clientData(array $payload, string $expectedType): array
    {
        $binary = $this->decodeRequired($payload, 'client_data_json');
        $decoded = json_decode($binary, true);

        if (! is_array($decoded) || ($decoded['type'] ?? null) !== $expectedType) {
            throw new WebAuthnCeremonyException(WebAuthnAssertionResult::INVALID_CHALLENGE, 'Data browser passkey tidak valid.');
        }

        $origin = rtrim(trim((string) ($decoded['origin'] ?? '')), '/');
        $expectedOrigin = rtrim((string) config('webauthn.origin'), '/');

        if ($origin === '' || ! hash_equals($expectedOrigin, $origin)) {
            throw new WebAuthnCeremonyException('invalid_origin', 'Domain passkey tidak sesuai. Buka aplikasi melalui alamat resmi SMA AFBS.');
        }

        $challenge = trim((string) ($decoded['challenge'] ?? ''));

        if ($challenge === '') {
            throw new WebAuthnCeremonyException(WebAuthnAssertionResult::INVALID_CHALLENGE, 'Challenge browser tidak ditemukan.');
        }

        return ['binary' => $binary, 'challenge' => $challenge];
    }

    private function assertChallengeMatches(WebAuthnChallenge $record, string $clientChallenge): void
    {
        if (! hash_equals((string) $record->challenge_hash, hash('sha256', $clientChallenge))) {
            throw new WebAuthnCeremonyException(WebAuthnAssertionResult::INVALID_CHALLENGE, 'Challenge passkey tidak cocok.');
        }
    }

    private function decodeRequired(array $payload, string $key): string
    {
        $value = trim((string) ($payload[$key] ?? ''));

        if ($value === '') {
            throw new WebAuthnCeremonyException(WebAuthnAssertionResult::INVALID_CHALLENGE, 'Payload passkey belum lengkap.');
        }

        try {
            return ByteBuffer::fromBase64Url($value)->getBinaryString();
        } catch (Throwable) {
            throw new WebAuthnCeremonyException(WebAuthnAssertionResult::INVALID_CHALLENGE, 'Encoding passkey tidak valid.');
        }
    }

    private function makeWebAuthn(): WebAuthn
    {
        return new WebAuthn(
            rpName: (string) config('webauthn.rp_name', 'SMA AFBS'),
            rpId: (string) config('webauthn.rp_id', 'app.smaafbs.sch.id'),
            allowedFormats: ['none'],
            useBase64UrlEncoding: true,
        );
    }

    private function normalizeOptions(object $options): array
    {
        return json_decode(json_encode($options, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    }

    private function normalizeCredentialId(mixed $credentialId): string
    {
        if (is_object($credentialId) && method_exists($credentialId, 'jsonSerialize')) {
            $credentialId = $credentialId->jsonSerialize();
        }

        if (is_string($credentialId) && preg_match('/^[A-Za-z0-9_-]+$/', $credentialId) === 1) {
            return $credentialId;
        }

        if (is_string($credentialId) && $credentialId !== '') {
            return rtrim(strtr(base64_encode($credentialId), '+/', '-_'), '=');
        }

        throw new WebAuthnCeremonyException(WebAuthnAssertionResult::INVALID_CHALLENGE, 'Credential passkey tidak valid.');
    }

    private function normalizeAaguid(mixed $aaguid): ?string
    {
        if (is_object($aaguid) && method_exists($aaguid, 'getHex')) {
            return strtolower((string) $aaguid->getHex());
        }

        if (is_string($aaguid) && $aaguid !== '') {
            return preg_match('/^[a-fA-F0-9-]+$/', $aaguid) === 1 ? strtolower($aaguid) : strtolower(bin2hex($aaguid));
        }

        return null;
    }

    private function deviceName(?string $requested): string
    {
        $requested = Str::limit(trim((string) $requested), 100, '');

        if ($requested !== '') {
            return $requested;
        }

        $agent = (string) request()->userAgent();

        return match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac') => 'Mac',
            default => 'Perangkat passkey',
        };
    }

    private function transports(mixed $items): array
    {
        return collect(is_array($items) ? $items : [])
            ->map(fn ($item): string => trim((string) $item))
            ->filter(fn (string $item): bool => in_array($item, ['internal', 'usb', 'nfc', 'ble', 'hybrid'], true))
            ->unique()
            ->values()
            ->all();
    }

    private function userHandleBinary(User $user): string
    {
        return 'user:'.$user->getKey();
    }

    private function userHandleEncoded(User $user): string
    {
        return rtrim(strtr(base64_encode($this->userHandleBinary($user)), '+/', '-_'), '=');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function mapLibraryException(WebAuthnException $exception): WebAuthnCeremonyException
    {
        return match ($exception->getMessage()) {
            'invalid origin' => new WebAuthnCeremonyException('invalid_origin', 'Domain passkey tidak sesuai.'),
            'invalid signature' => new WebAuthnCeremonyException('invalid_signature', 'Tanda tangan passkey tidak valid.'),
            'signature counter not valid' => new WebAuthnCeremonyException(WebAuthnAssertionResult::SIGN_COUNT_REGRESSION, 'Passkey terdeteksi tidak aman dan perlu didaftarkan ulang.'),
            'challenge not found', 'invalid challenge' => new WebAuthnCeremonyException(WebAuthnAssertionResult::INVALID_CHALLENGE, 'Sesi passkey sudah kedaluwarsa. Coba lagi.'),
            default => new WebAuthnCeremonyException('verification_failed', 'Verifikasi sidik jari/passkey gagal. Silakan coba lagi.'),
        };
    }

    private function markFailure(string $challengeId, string $status): void
    {
        WebAuthnChallenge::query()
            ->where('challenge_id', trim($challengeId))
            ->whereNull('used_at')
            ->update([
                'cancelled_at' => now(),
                'failure_reason' => Str::limit($status, 64, ''),
                'updated_at' => now(),
            ]);
    }

    private function logFailure(string $ceremony, string $challengeId, string $status): void
    {
        Log::warning('WebAuthn ceremony rejected.', [
            'ceremony' => $ceremony,
            'status' => Str::limit($status, 64, ''),
            'challenge_ref' => substr(hash('sha256', $challengeId), 0, 12),
        ]);
    }
}
