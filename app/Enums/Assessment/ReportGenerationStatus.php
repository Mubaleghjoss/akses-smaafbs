<?php

namespace App\Enums\Assessment;

enum ReportGenerationStatus: string
{
    case NOT_SCHEDULED = 'not_scheduled';
    case READY = 'ready';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::NOT_SCHEDULED => 'Belum dijadwalkan',
            self::READY => 'Siap diunduh',
            self::PENDING => 'Menunggu',
            self::PROCESSING => 'Diproses',
            self::COMPLETED => 'Selesai',
            self::FAILED => 'Gagal',
            self::CANCELLED => 'Dihentikan',
            self::EXPIRED => 'Cache kedaluwarsa',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::READY, self::COMPLETED, self::FAILED, self::CANCELLED, self::EXPIRED], true);
    }
}
