<?php

namespace App\Support\Auth\WebAuthn;

class WebAuthnChallengeIssueResult
{
    public const ISSUED = 'issued';

    public const UNSUPPORTED_BROWSER = 'unsupported_browser';

    public function __construct(
        public readonly string $status,
        public readonly string $challengeId,
        public readonly ?string $challenge,
        public readonly bool $canFallbackToPassword,
    ) {}

    public function issued(): bool
    {
        return $this->status === self::ISSUED;
    }
}
