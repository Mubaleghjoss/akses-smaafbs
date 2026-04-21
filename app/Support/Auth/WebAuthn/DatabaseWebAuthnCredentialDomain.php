<?php

namespace App\Support\Auth\WebAuthn;

use App\Contracts\Auth\WebAuthnCredentialDomain;
use App\Models\User;
use App\Models\WebAuthnCredential;
use DomainException;
use Illuminate\Support\Collection;

class DatabaseWebAuthnCredentialDomain implements WebAuthnCredentialDomain
{
    public function enroll(User $user, array $payload): WebAuthnCredential
    {
        $credentialId = trim((string) ($payload['credential_id'] ?? ''));

        if ($credentialId === '') {
            throw new DomainException('WebAuthn credential_id wajib diisi.');
        }

        $publicKey = trim((string) ($payload['public_key'] ?? ''));

        if ($publicKey === '') {
            throw new DomainException('WebAuthn public_key wajib diisi.');
        }

        $credential = WebAuthnCredential::query()
            ->where('credential_id', $credentialId)
            ->first();

        if ($credential && (int) $credential->user_id !== (int) $user->getKey()) {
            throw new DomainException('Credential sudah terdaftar untuk pengguna lain.');
        }

        $credential ??= new WebAuthnCredential;

        $credential->forceFill([
            'user_id' => $user->getKey(),
            'credential_id' => $credentialId,
            'public_key' => $publicKey,
            'transports' => collect($payload['transports'] ?? [])
                ->map(fn ($transport): string => trim((string) $transport))
                ->filter()
                ->values()
                ->all(),
            'aaguid' => $this->nullableTrimmedString($payload['aaguid'] ?? null),
            'label' => $this->nullableTrimmedString($payload['label'] ?? null),
            'sign_count' => max(0, (int) ($payload['sign_count'] ?? 0)),
            'revoked_at' => null,
        ]);

        $credential->save();

        return $credential;
    }

    public function listForUser(User $user, bool $includeRevoked = false): Collection
    {
        $query = WebAuthnCredential::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('id');

        if (! $includeRevoked) {
            $query->whereNull('revoked_at');
        }

        return $query->get();
    }

    public function revoke(User $user, string $credentialId): bool
    {
        $credential = WebAuthnCredential::query()
            ->where('user_id', $user->getKey())
            ->where('credential_id', $credentialId)
            ->whereNull('revoked_at')
            ->first();

        if (! $credential) {
            return false;
        }

        $credential->forceFill([
            'revoked_at' => now(),
        ])->save();

        return true;
    }

    public function findActiveByCredentialId(string $credentialId): ?WebAuthnCredential
    {
        return WebAuthnCredential::query()
            ->where('credential_id', $credentialId)
            ->whereNull('revoked_at')
            ->first();
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
