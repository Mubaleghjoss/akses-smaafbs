<?php

namespace App\Support\StudentSync;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

class StudentSyncMergePolicy
{
    /** @var array<int, string> */
    private const BOOLEAN_FIELDS = ['is_active', 'is_boarding'];

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     * @param  array<int, string>  $sharedColumns
     * @return array<string, mixed>
     */
    public function patch(array $source, array $target, array $sharedColumns): array
    {
        $denied = array_flip(config('student_sync.denied_fields', []));
        $patch = [];

        foreach ($sharedColumns as $column) {
            if (isset($denied[$column]) || ! array_key_exists($column, $source)) {
                continue;
            }

            $value = $this->normalize($column, $source[$column]);

            if ($this->isEmpty($value)) {
                continue;
            }

            $current = $this->normalize($column, $target[$column] ?? null);

            if ($this->equivalent($column, $value, $current)) {
                continue;
            }

            $patch[$column] = $value;
        }

        return $patch;
    }

    private function normalize(string $column, mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && $this->isDateColumn($column)) {
            try {
                return CarbonImmutable::parse($value)->format('Y-m-d');
            } catch (Throwable) {
                return $value;
            }
        }

        return $value;
    }

    private function equivalent(string $column, mixed $source, mixed $target): bool
    {
        if (in_array($column, self::BOOLEAN_FIELDS, true)) {
            $sourceBoolean = $this->normalizeBoolean($source);
            $targetBoolean = $this->normalizeBoolean($target);

            if ($sourceBoolean !== null && $targetBoolean !== null) {
                return $sourceBoolean === $targetBoolean;
            }
        }

        return $source === $target;
    }

    private function normalizeBoolean(mixed $value): ?bool
    {
        return match ($value) {
            true, 1, '1', 'true' => true,
            false, 0, '0', 'false' => false,
            default => null,
        };
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || (is_string($value) && $value === '');
    }

    private function isDateColumn(string $column): bool
    {
        return str_contains($column, 'tanggal')
            || str_ends_with($column, '_date')
            || str_ends_with($column, '_at');
    }
}
