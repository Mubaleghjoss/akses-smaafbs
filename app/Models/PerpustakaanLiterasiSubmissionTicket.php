<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerpustakaanLiterasiSubmissionTicket extends Model
{
    public const STATUS_WAITING = 'waiting';

    public const STATUS_ADMITTED = 'admitted';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $table = 'perpustakaan_literasi_submission_tickets';

    protected $guarded = [];

    protected $casts = [
        'requested_at' => 'datetime',
        'admitted_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
