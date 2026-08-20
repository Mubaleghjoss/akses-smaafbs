<?php

namespace App\Support\StudentSync;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

class StudentSyncMergePolicy
{
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

            if ($this->equivalent($value, $current)) {
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

    private function equivalent(mixed $source, mixed $target): bool
    {
        if (is_bool($source) || is_bool($target)) {
            return filter_var($source, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                === filter_var($target, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }

        return $source === $target;
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
