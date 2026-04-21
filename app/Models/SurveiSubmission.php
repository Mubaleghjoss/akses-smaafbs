<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveiSubmission extends Model
{
    protected $table = 'survei_submissions';

    protected $guarded = [];

    protected $casts = [
        'answers' => 'array',
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function survei(): BelongsTo
    {
        return $this->belongsTo(Survei::class, 'survei_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(SurveiTarget::class, 'survei_target_id');
    }

    public function answerForQuestion(SurveiQuestion $question): mixed
    {
        return data_get($this->answers ?? [], (string) $question->getKey());
    }
}
