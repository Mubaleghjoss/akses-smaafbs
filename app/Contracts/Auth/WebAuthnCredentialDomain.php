<?php

namespace App\Contracts\Auth;

use App\Models\User;
use App\Models\WebAuthnCredential;
use Illuminate\Support\Collection;

interface WebAuthnCredentialDomain
{
    /**
     * @param  array{
     *     credential_id: string,
     *     public_key: string,
     *     transports?: array<int, string>|null,
     *     aaguid?: ?string,
     *     label?: ?string,
     *     sign_count?: ?int
     * }  $payload
     */
    public function enroll(User $user, array $payload): WebAuthnCredential;

    /**
     * @return Collection<int, WebAuthnCredential>
     */
    public function listForUser(User $user, bool $includeRevoked = false): Collection;

    public function revoke(User $user, string $credentialId): bool;

    public function findActiveByCredentialId(string $credentialId): ?WebAuthnCredential;
}
