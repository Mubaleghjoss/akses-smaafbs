<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerpustakaanLiterasiSubmissionQueueState extends Model
{
    public $incrementing = false;

    protected $table = 'perpustakaan_literasi_submission_queue_states';

    protected $primaryKey = 'scope';

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'average_duration_ms' => 'integer',
        'last_submission_activity_at' => 'datetime',
    ];
}
