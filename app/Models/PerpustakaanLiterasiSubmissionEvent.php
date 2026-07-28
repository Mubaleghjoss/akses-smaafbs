<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerpustakaanLiterasiSubmissionEvent extends Model
{
    protected $table = 'perpustakaan_literasi_submission_events';

    protected $guarded = [];

    protected $casts = [
        'retry_statuses' => 'array',
        'context' => 'array',
        'occurred_at' => 'datetime',
    ];
}
