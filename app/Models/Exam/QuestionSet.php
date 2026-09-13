<?php

namespace App\Models\Exam;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionSet extends Model
{
    protected $table = 'exam_question_sets';
    protected $guarded = [];

    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function questions(): HasMany { return $this->hasMany(Question::class)->orderBy('position'); }
    public function schedules(): HasMany { return $this->hasMany(Schedule::class); }

    public function isOwnedBy(User $user): bool
    {
        return $user->hasFullAdminAccess() || $user->hasRole('kurikulum') || $this->teacher_id === $user->id;
    }
}
