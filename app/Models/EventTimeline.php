<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventTimeline extends Model
{
    protected $table = 'event_timeline';

    protected $guarded = [];

    protected $casts = [
        'dokumentasi' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
