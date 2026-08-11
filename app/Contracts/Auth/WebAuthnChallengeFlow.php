<?php

namespace App\Contracts\Auth;

use App\Models\User;
use App\Models\WebAuthnCredential;
use App\Support\Auth\WebAuthn\WebAuthnAssertionResult;
use App\Support\Auth\WebAuthn\WebAuthnChallengeIssueResult;

interface WebAuthnChallengeFlow
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function issueRegistrationChallenge(User $user, bool $browserSupported, array $context = []): WebAuthnChallengeIssueResult;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyRegistration(User $user, string $challengeId, array $payload, ?string $deviceName = null): WebAuthnCredential;

    /**
     * @param  array<string, mixed>  $context
     */
    public function issueDiscoverableAssertionChallenge(bool $browserSupported, array $context = []): WebAuthnChallengeIssueResult;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyDiscoverableAssertion(string $challengeId, string $credentialId, array $payload): WebAuthnAssertionResult;

    /**
     * @param  array<string, mixed>  $context
     */
    public function issueAssertionChallenge(User $user, bool $browserSupported, array $context = []): WebAuthnChallengeIssueResult;

    public function cancel(string $challengeId, string $reason = 'ceremony_cancelled'): void;

    /**
     * @param  array{
     *   sign_count?: int|null,
     *   credential_id?: string,
     *   raw_id?: string,
     *   client_data_json?: string,
     *   authenticator_data?: string,
     *   signature?: string,
     *   user_handle?: string
     * }  $payload
     */
    public function verifyAssertion(
        string $challengeId,
        string $clientChallenge,
        string $credentialId,
        array $payload = [],
    ): WebAuthnAssertionResult;
}
