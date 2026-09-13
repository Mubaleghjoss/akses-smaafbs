<?php

namespace App\Models\Exam;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $table = 'exam_events';
    protected $guarded = [];
    protected function casts(): array { return ['metadata' => 'array', 'occurred_at' => 'datetime']; }
}
