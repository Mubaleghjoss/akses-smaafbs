<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoardingStudent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardingKonselingMt extends Model
{
    use BelongsToBoardingStudent;

    protected static ?string $pamongOwnershipColumn = 'pamong_user_id';

    protected $table = 'boarding_konseling_mts';

    protected $guarded = [];

    protected $casts = [
        'tanggal_konseling' => 'date',
        'lampiran' => 'array',
        'pamong_user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if (blank($record->pamong_user_id) && auth()->user()?->isBoardingPamong()) {
                $record->pamong_user_id = auth()->id();
            }

            if ($record->pamong_user_id) {
                $record->konselor = User::query()->whereKey($record->pamong_user_id)->value('name') ?: $record->konselor;
            }
        });
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(DataSiswa::class, 'siswa_id');
    }

    public function pamongUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pamong_user_id');
    }
}
