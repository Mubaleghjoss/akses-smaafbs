<?php

namespace App\Support\Auth\WebAuthn;

class WebAuthnAssertionResult
{
    public const VERIFIED = 'verified';

    public const UNSUPPORTED_BROWSER = 'unsupported_browser';

    public const INVALID_CHALLENGE = 'invalid_challenge';

    public const CEREMONY_CANCELLED = 'ceremony_cancelled';

    public const CREDENTIAL_NOT_FOUND = 'credential_not_found';

    public const CREDENTIAL_REVOKED = 'credential_revoked';

    public const SIGN_COUNT_REGRESSION = 'sign_count_regression';

    public function __construct(
        public readonly string $status,
        public readonly bool $canFallbackToPassword,
    ) {}

    public function verified(): bool
    {
        return $this->status === self::VERIFIED;
    }
}
