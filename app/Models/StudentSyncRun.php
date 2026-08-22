<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StudentSyncRun extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'counts' => 'array',
            'field_summary' => 'array',
            'result_summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
