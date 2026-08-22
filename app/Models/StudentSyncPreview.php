<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StudentSyncPreview extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'encrypted_payload' => 'encrypted:array',
            'expires_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }
}
