<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotUser extends Model
{
    protected $table = 'hotspot_users';

    protected $fillable = [
        'username', 'password', 'profile', 'durasi', 'note', 'disabled', 'source',
        'role', 'nama', 'kelas', 'input_mode', 'category_id',
    ];

    protected $casts = [
        'durasi' => 'integer',
        'disabled' => 'boolean',
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
}
