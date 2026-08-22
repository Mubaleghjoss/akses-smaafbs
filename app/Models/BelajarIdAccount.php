<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BelajarIdAccount extends Model
{
    protected $table = 'belajar_id_accounts';

    protected $fillable = [
        'role', 'nama', 'status', 'email', 'password', 'category_id',
    ];

    protected $casts = [
        'category_id' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AccountCategory::class, 'category_id');
    }

    public function scopeSiswa($query)
    {
        return $query->where('role', 'siswa');
    }

    public function scopeGuru($query)
    {
        return $query->where('role', 'guru');
    }

    /** Normalisasi STATUS Excel -> role. guru/tendik => guru, selain itu siswa. */
    public static function roleFromStatus(?string $status): string
    {
        $s = strtolower(trim((string) $status));

        return in_array($s, ['guru', 'tendik'], true) ? 'guru' : 'siswa';
    }
}
