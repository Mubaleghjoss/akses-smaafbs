<?php

namespace App\Enums\Assessment;

enum ReportGenerationStatus: string
{
    case NOT_SCHEDULED = 'not_scheduled';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NOT_SCHEDULED => 'Belum dijadwalkan',
            self::PENDING => 'Menunggu',
            self::PROCESSING => 'Diproses',
            self::COMPLETED => 'Selesai',
            self::FAILED => 'Gagal',
            self::CANCELLED => 'Dihentikan',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::CANCELLED], true);
    }
}
