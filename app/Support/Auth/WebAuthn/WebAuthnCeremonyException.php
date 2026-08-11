<?php

namespace App\Support\Auth\WebAuthn;

use DomainException;

class WebAuthnCeremonyException extends DomainException
{
    public function __construct(
        public readonly string $status,
        string $message,
    ) {
        parent::__construct($message);
    }
}
