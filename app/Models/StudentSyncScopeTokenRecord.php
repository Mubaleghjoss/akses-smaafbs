<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentSyncScopeTokenRecord extends Model
{
    protected $table = 'student_sync_scope_tokens';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'encrypted_student_ids' => 'encrypted:array',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
