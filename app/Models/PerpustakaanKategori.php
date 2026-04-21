<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerpustakaanKategori extends Model
{
    protected $table = 'perpustakaan_kategori';

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function searchOptionLabels(string $search = '', int $limit = 50): array
    {
        $query = static::query()->select(['id', 'nama_kategori']);
        $search = trim($search);

        if ($search !== '') {
            $query->where('nama_kategori', 'like', '%'.$search.'%');
        }

        return $query
            ->orderBy('nama_kategori')
            ->limit($limit)
            ->pluck('nama_kategori', 'id')
            ->toArray();
    }

    public static function resolveOptionLabel(mixed $value): ?string
    {
        $id = (int) $value;

        if ($id <= 0) {
            return null;
        }

        return static::query()->whereKey($id)->value('nama_kategori');
    }
}
