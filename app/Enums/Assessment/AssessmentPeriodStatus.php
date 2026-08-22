<?php

namespace App\Enums\Assessment;

enum AssessmentPeriodStatus: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case ENTRY_CLOSED = 'entry_closed';
    case VERIFICATION = 'verification';
    case LOCKED = 'locked';
    case PUBLISHED = 'published';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf',
            self::OPEN => 'Dibuka',
            self::ENTRY_CLOSED => 'Input Ditutup',
            self::VERIFICATION => 'Verifikasi',
            self::LOCKED => 'Dikunci',
            self::PUBLISHED => 'Diterbitkan',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::DRAFT => $target === self::OPEN,
            self::OPEN => $target === self::ENTRY_CLOSED,
            self::ENTRY_CLOSED => $target === self::VERIFICATION,
            self::VERIFICATION => $target === self::LOCKED,
            self::LOCKED => $target === self::PUBLISHED,
            self::PUBLISHED => false,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
