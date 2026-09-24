<?php

namespace App\Models\Assessment;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentExtracurricularScore extends Model
{
    public const PREDICATES = ['A', 'B', 'C', 'D'];
    public const STATUSES = ['draft', 'submitted', 'verified', 'returned'];

    protected $fillable = ['assessment_extracurricular_participant_id', 'predicate', 'description', 'status', 'submitted_at', 'submitted_by', 'verified_at', 'verified_by', 'returned_at', 'returned_by', 'returned_reason', 'updated_by'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'verified_at' => 'datetime', 'returned_at' => 'datetime'];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(AssessmentExtracurricularParticipant::class, 'assessment_extracurricular_participant_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
