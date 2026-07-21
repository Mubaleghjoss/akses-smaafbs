<?php

namespace App\Exceptions;

use RuntimeException;

class LiteracySubmissionQueueBusy extends RuntimeException
{
    public function __construct(public readonly array $queuePayload)
    {
        parent::__construct('Pengiriman sedang menunggu antrean.');
    }
}
