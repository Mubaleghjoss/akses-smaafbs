<?php

namespace App\Models\Exam;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    protected $table = 'exam_answers';
    protected $guarded = [];
    protected function casts(): array { return ['answer' => 'array', 'is_correct' => 'boolean', 'saved_at' => 'datetime']; }
    public function attempt(): BelongsTo { return $this->belongsTo(Attempt::class); }
    public function question(): BelongsTo { return $this->belongsTo(Question::class); }
}
