<?php

namespace App\Support\Assessment;

use App\Models\Assessment\AssessmentExtracurricularParticipant;
use App\Models\Assessment\AssessmentExtracurricularScore;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssessmentExtracurricularWorkflow
{
    public function save(User $actor, AssessmentExtracurricularParticipant $participant, ?string $predicate, ?string $description = null): AssessmentExtracurricularScore
    {
        $this->authorizeTeacher($actor, $participant);
        $predicate = strtoupper(trim((string) $predicate));
        if ($predicate !== '' && ! in_array($predicate, AssessmentExtracurricularScore::PREDICATES, true)) {
            throw ValidationException::withMessages(['predicate' => 'Predikat harus A, B, C, atau D.']);
        }

        $score = $participant->score()->firstOrNew();
        abort_if($score->exists && in_array($score->status, ['submitted', 'verified'], true), 422, 'Nilai yang sudah dikirim tidak dapat diedit.');
        $score->fill(['predicate' => $predicate ?: null, 'description' => filled($description) ? trim($description) : null, 'status' => 'draft', 'updated_by' => $actor->getKey()])->save();

        return $score;
    }

    public function submit(User $actor, AssessmentExtracurricularParticipant $participant): AssessmentExtracurricularScore
    {
        $this->authorizeTeacher($actor, $participant);
        $score = $participant->score;
        abort_unless($score && in_array($score->predicate, AssessmentExtracurricularScore::PREDICATES, true), 422, 'Lengkapi predikat sebelum dikirim.');
        abort_unless(in_array($score->status, ['draft', 'returned'], true), 422, 'Status nilai tidak dapat dikirim.');
        $score->update(['status' => 'submitted', 'submitted_at' => now(), 'submitted_by' => $actor->getKey(), 'returned_at' => null, 'returned_by' => null, 'returned_reason' => null]);

        return $score->fresh();
    }

    public function verify(User $actor, AssessmentExtracurricularScore $score): AssessmentExtracurricularScore
    {
        $this->authorizeManager($actor);
        abort_unless($score->status === 'submitted', 422, 'Hanya nilai terkirim yang dapat diverifikasi.');
        $score->update(['status' => 'verified', 'verified_at' => now(), 'verified_by' => $actor->getKey(), 'returned_at' => null, 'returned_by' => null, 'returned_reason' => null]);

        return $score->fresh();
    }

    public function return(User $actor, AssessmentExtracurricularScore $score, string $reason): AssessmentExtracurricularScore
    {
        $this->authorizeManager($actor);
        abort_unless(in_array($score->status, ['submitted', 'verified'], true), 422, 'Status nilai tidak dapat dikembalikan.');
        if (blank(trim($reason))) {
            throw ValidationException::withMessages(['reason' => 'Alasan pengembalian wajib diisi.']);
        }
        $score->update(['status' => 'returned', 'returned_at' => now(), 'returned_by' => $actor->getKey(), 'returned_reason' => trim($reason), 'verified_at' => null, 'verified_by' => null]);

        return $score->fresh();
    }

    private function authorizeTeacher(User $actor, AssessmentExtracurricularParticipant $participant): void
    {
        $allowed = $actor->hasFullAdminAccess() || $actor->hasRole('kurikulum') || $participant->extracurricular()->whereHas('teachers', fn ($query) => $query->whereKey($actor->getKey()))->exists();
        abort_unless($allowed && ($actor->hasFullAdminAccess() || ($actor->canViewModule('penilaian') && $actor->can('penilaian.input'))), 403);
    }

    private function authorizeManager(User $actor): void
    {
        abort_unless($actor->hasFullAdminAccess() || ($actor->canViewModule('penilaian') && ($actor->can('penilaian.verify') || $actor->hasRole('kurikulum'))), 403);
    }
}
