<?php

namespace App\Models\Exam;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attempt extends Model
{
    protected $table = 'exam_attempts';
    protected $guarded = [];
    protected function casts(): array { return ['started_at' => 'datetime', 'submitted_at' => 'datetime', 'final_score' => 'decimal:2']; }
    public function schedule(): BelongsTo { return $this->belongsTo(Schedule::class); }
    public function studentToken(): BelongsTo { return $this->belongsTo(StudentToken::class); }
    public function answers(): HasMany { return $this->hasMany(Answer::class); }
    public function events(): HasMany { return $this->hasMany(Event::class); }
}
