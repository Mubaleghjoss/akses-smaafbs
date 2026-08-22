<?php

namespace App\Enums\Assessment;

enum ScoreSource: string
{
    case MANUAL = 'manual';
    case IMPORTED = 'imported';
    case ASTS_SNAPSHOT = 'asts_snapshot';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Input Guru',
            self::IMPORTED => 'Impor',
            self::ASTS_SNAPSHOT => 'Snapshot ASTS',
        };
    }
}
