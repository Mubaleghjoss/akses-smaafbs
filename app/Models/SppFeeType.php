<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SppFeeType extends Model
{
    protected $table = 'spp_fee_types';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'amount' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function searchOptionLabels(string $search = '', int $limit = 50): array
    {
        $query = static::query()->select(['id', 'name']);
        $search = trim($search);

        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return $query
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name', 'id')
            ->toArray();
    }

    public static function resolveOptionLabel(mixed $value): ?string
    {
        $id = (int) $value;

        if ($id <= 0) {
            return null;
        }

        return static::query()->whereKey($id)->value('name');
    }
}
