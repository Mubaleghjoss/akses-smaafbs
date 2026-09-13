<?php

namespace App\Models\Exam;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class Schedule extends Model
{
    protected $table = 'exam_schedules';
    protected $guarded = [];
    protected $hidden = ['supervisor_code_hash'];
    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean']; }
    public function questionSet(): BelongsTo { return $this->belongsTo(QuestionSet::class); }
    public function tokens(): HasMany { return $this->hasMany(StudentToken::class); }
    public function attempts(): HasMany { return $this->hasMany(Attempt::class); }
    public function supervisorCodeMatches(string $code): bool { return Hash::check($code, $this->supervisor_code_hash); }
}
