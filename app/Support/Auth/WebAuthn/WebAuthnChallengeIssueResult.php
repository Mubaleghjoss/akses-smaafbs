<?php

namespace App\Support\Auth\WebAuthn;

class WebAuthnChallengeIssueResult
{
    public const ISSUED = 'issued';

    public const UNSUPPORTED_BROWSER = 'unsupported_browser';

    public const DISABLED = 'disabled';

    public function __construct(
        public readonly string $status,
        public readonly string $challengeId,
        public readonly ?string $challenge,
        public readonly bool $canFallbackToPassword,
        /** @var array<string, mixed>|null */
        public readonly ?array $publicKeyOptions = null,
    ) {}

    public function issued(): bool
    {
        return $this->status === self::ISSUED;
    }
}
