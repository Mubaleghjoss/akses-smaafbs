<?php

namespace App\Exceptions;

use RuntimeException;

final class AssessmentReportRenderBusy extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds = 10)
    {
        parent::__construct('Server sedang menyiapkan PDF rapor lain.');
    }
}
