<?php

namespace App\Enums\Assessment;

enum ReportGenerationStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu',
            self::PROCESSING => 'Diproses',
            self::COMPLETED => 'Selesai',
            self::FAILED => 'Gagal',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED], true);
    }
}
