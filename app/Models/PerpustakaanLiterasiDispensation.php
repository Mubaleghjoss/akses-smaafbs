<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerpustakaanLiterasiDispensation extends Model
{
    use SoftDeletes;

    public const REASON_SICK = 'sick';

    public const REASON_MT_TEST = 'mt_test';

    public const REASON_PERMISSION = 'permission';

    protected $table = 'perpustakaan_literasi_dispensations';

    protected $guarded = [];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * @return array<string, string>
     */
    public static function reasonOptions(): array
    {
        return [
            self::REASON_PERMISSION => 'Izin',
            self::REASON_SICK => 'Sakit',
            self::REASON_MT_TEST => 'Tes MT',
        ];
    }

    public function reasonLabel(): string
    {
        return static::reasonOptions()[$this->reason] ?? 'Dispensasi';
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(PerpustakaanLiterasiMaterial::class, 'material_id')->withTrashed();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(DataSiswa::class, 'data_siswa_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
