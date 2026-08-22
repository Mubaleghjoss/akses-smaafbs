<?php

namespace App\Enums\Assessment;

enum AssignmentStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case RETURNED = 'returned';
    case VERIFIED = 'verified';
    case LOCKED = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf',
            self::SUBMITTED => 'Dikirim',
            self::RETURNED => 'Dikembalikan',
            self::VERIFIED => 'Terverifikasi',
            self::LOCKED => 'Dikunci',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::RETURNED], true);
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::DRAFT => $target === self::SUBMITTED,
            self::SUBMITTED => in_array($target, [self::RETURNED, self::VERIFIED], true),
            self::RETURNED => $target === self::SUBMITTED,
            self::VERIFIED => in_array($target, [self::RETURNED, self::LOCKED], true),
            self::LOCKED => false,
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
