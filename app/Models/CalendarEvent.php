<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    protected $table = 'calendar_events';

    protected $guarded = [];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'all_day' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
