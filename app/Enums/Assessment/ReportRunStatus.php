<?php

namespace App\Enums\Assessment;

enum ReportRunStatus: string
{
    case PREPARED = 'prepared';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PREPARED => 'Belum dijadwalkan',
            self::RUNNING => 'Sedang diproses',
            self::COMPLETED => 'Selesai',
            self::CANCELLED => 'Dihentikan',
            self::FAILED => 'Perlu diperiksa',
        };
    }
}
