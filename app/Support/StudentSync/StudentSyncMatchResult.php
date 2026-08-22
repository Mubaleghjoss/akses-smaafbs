<?php

namespace App\Support\StudentSync;

use App\Models\DataSiswa;

final class StudentSyncMatchResult
{
    public const MATCHED = 'matched';

    public const CONFLICT = 'conflict';

    public const NOT_FOUND = 'not_found';

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public readonly string $status,
        public readonly ?DataSiswa $matched,
        public readonly string $reason,
        public readonly array $evidence = [],
    ) {}
}
