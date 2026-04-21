<?php

namespace App\Models\Concerns;

use App\Models\DataSiswa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToBoardingStudent
{
    public function scopeVisibleToUser(Builder $query, mixed $user): Builder
    {
        if (! $user instanceof User || ! $user->isBoardingPamong()) {
            return $query;
        }

        $query->whereHas('siswa', function (Builder $studentQuery) use ($user): void {
            DataSiswa::applyVisibleScope($studentQuery, $user);
        });

        if ($ownershipColumn = static::pamongOwnershipColumn()) {
            $query->where($ownershipColumn, $user->getKey());
        }

        return $query;
    }

    protected static function pamongOwnershipColumn(): ?string
    {
        return property_exists(static::class, 'pamongOwnershipColumn')
            ? static::$pamongOwnershipColumn
            : null;
    }
}
