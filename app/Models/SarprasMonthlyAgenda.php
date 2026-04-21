<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SarprasMonthlyAgenda extends Model
{
    public const STATUS_SUDAH = 'sudah';

    public const STATUS_BELUM = 'belum';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'bulan_agenda' => 'date',
            'urutan' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_SUDAH => 'Sudah',
            self::STATUS_BELUM => 'Belum',
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return static::statusOptions()[(string) $status] ?? ($status ?: '-');
    }
}
