<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardingHafalanPoint extends Model
{
    public const MATERI_OPTIONS = [
        'pegon_bacaan' => 'pegon_bacaan',
        'lambatan' => 'lambatan',
        'cepatan' => 'cepatan',
        'seleksi_saringan' => 'seleksi_saringan',
    ];

    protected $table = 'boarding_hafalan_points';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Set actor tracking when auth is available, but don't fail when not.
        static::creating(function (self $model): void {
            if (blank($model->created_by) && auth()->id()) {
                $model->created_by = auth()->id();
            }

            if (blank($model->updated_by) && auth()->id()) {
                $model->updated_by = auth()->id();
            }
        });

        static::saving(function (self $model): void {
            if (auth()->id()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(BoardingHafalanAssessment::class, 'boarding_hafalan_point_id');
    }

    public static function materiKeyOptions(): array
    {
        $databaseOptions = static::query()
            ->whereNotNull('materi_key')
            ->select('materi_key')
            ->distinct()
            ->orderBy('materi_key')
            ->pluck('materi_key', 'materi_key')
            ->toArray();

        return $databaseOptions + self::MATERI_OPTIONS;
    }
}
