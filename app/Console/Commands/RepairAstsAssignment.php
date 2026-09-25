<?php

namespace App\Console\Commands;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentScheme;
use App\Models\Assessment\TeachingAssignment;
use App\Models\User;
use App\Support\Assessment\AssessmentAuditLogger;
use App\Support\Assessment\AssessmentSchemeResolver;
use App\Support\Assessment\AstsSchemeComponents;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairAstsAssignment extends Command
{
    protected $signature = 'assessment:repair-asts-assignment
        {assignment : ID assignment periode yang akan diperbaiki}
        {--apply : Terapkan reset. Tanpa opsi ini perintah selalu dry-run}
        {--expected-period= : Guard ID periode yang diharapkan}
        {--expected-lock-version= : Guard lock version yang diharapkan}
        {--actor= : ID pengguna pelaksana untuk audit}
        {--reason= : Alasan reset, wajib saat --apply}';

    protected $description = 'Rencanakan atau reset aman satu assignment ASTS dan sinkronkan plotting guru bila tidak ambigu';

    public function handle(AstsSchemeComponents $components, AssessmentSchemeResolver $resolver, AssessmentAuditLogger $audit): int
    {
        $assignmentId = (int) $this->argument('assignment');
        $assignment = AssessmentPeriodAssignment::query()->with(['period', 'periodRombel', 'subject'])->find($assignmentId);
        if (! $assignment) {
            $this->error("Assignment #{$assignmentId} tidak ditemukan.");

            return self::FAILURE;
        }

        $actor = $this->actor();
        $candidate = $this->teachingCandidate($assignment);
        $plan = $this->plan($assignment, $candidate, $resolver, $components);
        $this->renderPlan($plan);

        if (! $this->option('apply')) {
            $this->comment('Dry-run: tidak ada data yang diubah. Tambahkan --apply --reason="..." untuk menerapkan.');

            return $plan['blocked'] === [] ? self::SUCCESS : self::FAILURE;
        }

        if (blank($this->option('reason'))) {
            $this->error('--reason wajib saat menggunakan --apply.');

            return self::FAILURE;
        }
        if (! $actor) {
            $this->error('--actor harus merujuk ke pengguna yang ada saat menggunakan --apply.');

            return self::FAILURE;
        }
        if ($plan['blocked'] !== []) {
            $this->error('Apply diblokir: '.implode(' ', $plan['blocked']));

            return self::FAILURE;
        }

        DB::transaction(function () use ($assignmentId, $actor, $audit, $components, $resolver): void {
            /** @var AssessmentPeriodAssignment $locked */
            $locked = AssessmentPeriodAssignment::query()->with(['period', 'periodRombel', 'subject'])->lockForUpdate()->findOrFail($assignmentId);
            $candidate = $this->teachingCandidate($locked, true);
            $plan = $this->plan($locked, $candidate, $resolver, $components);
            if ($plan['blocked'] !== []) {
                throw new \RuntimeException('Apply diblokir setelah penguncian: '.implode(' ', $plan['blocked']));
            }

            $before = $this->assignmentState($locked);
            $scheme = $this->findOrCreateScopedScheme($locked, $resolver);
            $components->normalize($scheme);
            $scoreCount = $locked->scores()->delete();
            $resultCount = $locked->results()->delete();

            $values = [
                'status' => AssignmentStatus::DRAFT,
                'submitted_at' => null, 'submitted_by' => null,
                'verified_at' => null, 'verified_by' => null,
                'returned_at' => null, 'returned_by' => null, 'returned_reason' => null,
                'locked_at' => null, 'locked_by' => null,
                'lock_version' => (int) $locked->lock_version + 1,
            ];
            if ($candidate instanceof TeachingAssignment) {
                $values += $this->teachingValues($locked, $candidate);
            }
            $locked->forceFill($values)->save();

            $audit->record(
                actor: $actor,
                event: 'assignment.asts_repaired_and_scores_reset',
                subject: $locked,
                oldValues: [...$before, 'deleted_score_count' => $scoreCount, 'deleted_result_count' => $resultCount],
                newValues: [...$this->assignmentState($locked), 'scheme_id' => $scheme->getKey(), 'components' => ['UH1', 'UH2', 'UH3', 'ASTS_MURNI']],
                reason: (string) $this->option('reason'),
            );
        }, 3);

        $this->info("Assignment #{$assignmentId} diperbaiki dan nilainya direset.");

        return self::SUCCESS;
    }

    private function actor(): ?User
    {
        $actorId = $this->option('actor');

        return filled($actorId) ? User::query()->find((int) $actorId) : null;
    }

    private function teachingCandidate(AssessmentPeriodAssignment $assignment, bool $lock = false): ?TeachingAssignment
    {
        $sourceRombelId = (int) ($assignment->periodRombel?->source_rombel_id ?? 0);
        if ($sourceRombelId <= 0) {
            return null;
        }
        $query = TeachingAssignment::query()->with('category')
            ->where('assessment_semester_id', $assignment->period->assessment_semester_id)
            ->where('assessment_subject_id', $assignment->assessment_subject_id)
            ->where('rombel_id', $sourceRombelId)
            ->where('is_active', true);
        if ($lock) {
            $query->lockForUpdate();
        }
        $matches = $query->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** @return array{blocked: list<string>, score_count: int, result_count: int, scheme: ?AssessmentScheme, candidate_count: int} */
    private function plan(AssessmentPeriodAssignment $assignment, ?TeachingAssignment $candidate, AssessmentSchemeResolver $resolver, AstsSchemeComponents $components): array
    {
        $blocked = [];
        if ($assignment->period->type !== AssessmentType::ASTS) {
            $blocked[] = 'Periode bukan ASTS.';
        }
        if (! in_array($assignment->period->status, [AssessmentPeriodStatus::DRAFT, AssessmentPeriodStatus::OPEN], true)) {
            $blocked[] = 'Status periode harus Draf atau Dibuka.';
        }
        if (filled($this->option('expected-period')) && (int) $this->option('expected-period') !== (int) $assignment->assessment_period_id) {
            $blocked[] = 'expected-period tidak cocok.';
        }
        if (filled($this->option('expected-lock-version')) && (int) $this->option('expected-lock-version') !== (int) $assignment->lock_version) {
            $blocked[] = 'expected-lock-version tidak cocok.';
        }
        $candidateCount = TeachingAssignment::query()->where('assessment_semester_id', $assignment->period->assessment_semester_id)->where('assessment_subject_id', $assignment->assessment_subject_id)->where('rombel_id', $assignment->periodRombel?->source_rombel_id)->where('is_active', true)->count();
        if ($candidateCount !== 1) {
            $blocked[] = 'Plotting guru aktif tidak tunggal; guru tidak boleh disinkronkan secara tebakan.';
        }
        try {
            $scheme = $resolver->forAssignment($assignment);
        } catch (\Throwable) {
            $scheme = null;
        }

        return ['blocked' => $blocked, 'score_count' => $assignment->scores()->count(), 'result_count' => $assignment->results()->count(), 'scheme' => $scheme, 'candidate_count' => $candidateCount];
    }

    /** @param array{blocked: list<string>, score_count: int, result_count: int, scheme: ?AssessmentScheme, candidate_count: int} $plan */
    private function renderPlan(array $plan): void
    {
        $assignment = AssessmentPeriodAssignment::query()->with(['period', 'periodRombel'])->findOrFail((int) $this->argument('assignment'));
        $this->table(['Field', 'Nilai'], [
            ['Assignment', (string) $assignment->getKey()], ['Periode', "#{$assignment->assessment_period_id} {$assignment->period->type->value}/{$assignment->period->status->value}"],
            ['Mapel / kelas / guru', "{$assignment->subject_name_snapshot} / {$assignment->rombel_name_snapshot} / {$assignment->teacher_name_snapshot}"],
            ['Status / lock version', "{$assignment->status->value} / {$assignment->lock_version}"], ['Nilai / hasil', "{$plan['score_count']} / {$plan['result_count']}"],
            ['Kandidat plotting aktif', (string) $plan['candidate_count']], ['Skema saat ini', $plan['scheme'] ? "#{$plan['scheme']->id} {$plan['scheme']->name}" : 'Tidak dapat diresolusikan'],
            ['Komponen target', 'UH1, UH2, UH3, ASTS_MURNI'], ['Apply', $plan['blocked'] === [] ? 'DIIZINKAN' : 'DIBLOKIR: '.implode(' ', $plan['blocked'])],
        ]);
    }

    private function findOrCreateScopedScheme(AssessmentPeriodAssignment $assignment, AssessmentSchemeResolver $resolver): AssessmentScheme
    {
        $sourceRombelId = (int) $assignment->periodRombel->source_rombel_id;
        $scheme = AssessmentScheme::query()->where('assessment_period_id', $assignment->assessment_period_id)->where('assessment_subject_id', $assignment->assessment_subject_id)->where('assessment_period_rombel_id', $assignment->assessment_period_rombel_id)->where('source_rombel_id', $sourceRombelId)->lockForUpdate()->first();
        if ($scheme) {
            return $scheme;
        }
        $base = $resolver->forAssignment($assignment);

        return AssessmentScheme::query()->create([
            'assessment_period_id' => $assignment->assessment_period_id, 'assessment_subject_id' => $assignment->assessment_subject_id,
            'assessment_period_rombel_id' => $assignment->assessment_period_rombel_id, 'source_rombel_id' => $sourceRombelId,
            'name' => 'ASTS repair assignment #'.$assignment->getKey(), 'rounding_precision' => $base->rounding_precision,
            'minimum_score' => $base->minimum_score, 'maximum_score' => $base->maximum_score, 'settings' => $base->settings, 'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function teachingValues(AssessmentPeriodAssignment $assignment, TeachingAssignment $teaching): array
    {
        return ['source_teaching_assignment_id' => $teaching->getKey(), 'teacher_id' => $teaching->teacher_id, 'teacher_name_snapshot' => $teaching->teacher_name_snapshot, 'subject_group_code_snapshot' => $teaching->category->code, 'subject_group_name_snapshot' => $teaching->category->name, 'subject_group_sort_order_snapshot' => (int) $teaching->category->sort_order, 'rombel_name_snapshot' => $assignment->periodRombel->rombel_name_snapshot];
    }

    /** @return array<string, mixed> */
    private function assignmentState(AssessmentPeriodAssignment $assignment): array
    {
        return $assignment->only(['source_teaching_assignment_id', 'teacher_id', 'teacher_name_snapshot', 'status', 'lock_version', 'submitted_at', 'submitted_by', 'verified_at', 'verified_by', 'returned_at', 'returned_by', 'returned_reason', 'locked_at', 'locked_by']);
    }
}
