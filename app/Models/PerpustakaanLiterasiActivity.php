<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerpustakaanLiterasiActivity extends Model
{
    public const PURPOSE_LITERASI = 'literasi';

    public const PURPOSE_TUGAS = 'tugas';

    public const RESULT_PENDING = 'pending';

    public const RESULT_SUBMITTED = 'submitted';

    public const RESULT_NOT_REQUIRED = 'not_required';

    protected $table = 'perpustakaan_literasi_activities';

    protected $guarded = [];

    protected $casts = [
        'activity_at' => 'datetime',
        'result_submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(PerpustakaanBuku::class, 'book_id');
    }

    public static function purposeOptions(): array
    {
        return [
            self::PURPOSE_LITERASI => 'Literasi',
            self::PURPOSE_TUGAS => 'Tugas',
        ];
    }

    public static function purposeLabel(?string $purpose): string
    {
        return self::purposeOptions()[(string) $purpose] ?? ($purpose ?: '-');
    }

    public static function resultStatusOptions(): array
    {
        return [
            self::RESULT_PENDING => 'Menunggu hasil',
            self::RESULT_SUBMITTED => 'Hasil terkirim',
            self::RESULT_NOT_REQUIRED => 'Tidak wajib',
        ];
    }

    public static function resultStatusLabel(?string $status): string
    {
        return self::resultStatusOptions()[(string) $status] ?? ($status ?: '-');
    }
}
