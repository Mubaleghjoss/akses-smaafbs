<?php

namespace App\Models\Exam;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    public const TYPES = ['multiple_choice', 'multiple_response', 'true_false', 'essay'];

    protected $table = 'exam_questions';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['options' => 'array', 'answer_key' => 'array', 'weight' => 'decimal:2'];
    }

    public function questionSet(): BelongsTo { return $this->belongsTo(QuestionSet::class); }
}
