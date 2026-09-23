<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Assessment\AssessmentType;
use App\Exports\AssessmentAstsWorkbookExport;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\Assessment\AssessmentScore;
use App\Models\Assessment\HomeroomReport;
use App\Models\Assessment\StudentSubjectResult;
use App\Models\User;
use App\Support\Assessment\AssessmentAstsHomeroomRanking;
use App\Support\Assessment\AssessmentStatusScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssessmentAstsExportController
{
    public function status(Request $request, AssessmentPeriod $assessmentPeriod): BinaryFileResponse
    {
        $this->ensureAstsAndUser($assessmentPeriod);
        $user = $request->user();
        $assignments = app(AssessmentStatusScope::class)->apply(
            AssessmentPeriodAssignment::query()->where('assessment_period_id', $assessmentPeriod->getKey()),
            $user,
            (int) $assessmentPeriod->getKey(),
        )
            ->when($request->integer('rombel'), fn (Builder $query, int $id): Builder => $query->where('assessment_period_rombel_id', $id))
            ->when($request->integer('subject'), fn (Builder $query, int $id): Builder => $query->where('assessment_subject_id', $id))
            ->when($request->string('status')->toString() !== '' && $request->string('status')->toString() !== 'all', fn (Builder $query): Builder => $query->where('status', $request->string('status')->toString()))
            ->orderBy('rombel_name_snapshot')->orderBy('subject_name_snapshot')->get();

        $data = $this->assessmentData($assessmentPeriod, $assignments);
        $summary = [[
            'Periode', 'Jenis', 'Kelas', 'Mapel', 'Guru', 'Status Assignment', 'Jumlah Siswa', 'Siswa Lengkap', 'Siswa Belum Lengkap', 'Persentase', 'Diperbarui', 'Oleh',
        ]];
        $problems = [['Kelas', 'Mapel', 'Guru', 'Siswa', 'NIS/NISN', 'Masalah']];
        foreach ($assignments as $assignment) {
            $students = $data['students']->get($assignment->assessment_period_rombel_id, collect());
            $results = $data['results']->where('assessment_period_assignment_id', $assignment->getKey())->keyBy('assessment_period_student_id');
            $complete = $results->filter(fn (StudentSubjectResult $result): bool => $result->final_score !== null)->count();
            $summary[] = [$assessmentPeriod->name, 'ASTS', $assignment->rombel_name_snapshot, $assignment->subject_name_snapshot, $assignment->teacher_name_snapshot, $assignment->status->label(), $students->count(), $complete, $students->count() - $complete, $students->count() ? round($complete / $students->count() * 100, 2).'%' : '0%', $assignment->updated_at?->format('d/m/Y H:i'), $assignment->submitter?->name ?: $assignment->verifier?->name ?: '-'];
            if (! in_array($assignment->status->value, ['submitted', 'verified', 'locked'], true)) {
                $problems[] = [$assignment->rombel_name_snapshot, $assignment->subject_name_snapshot, $assignment->teacher_name_snapshot, '-', '-', 'Guru belum submit'];
            }
            foreach ($students as $student) {
                $row = $this->detailRow($assignment, $student, $results->get($student->getKey()), $data['scores']);
                foreach ($this->problems($assignment, $row) as $problem) {
                    $problems[] = [$assignment->rombel_name_snapshot, $assignment->subject_name_snapshot, $assignment->teacher_name_snapshot, $student->student_name_snapshot, $student->nis_snapshot ?: $student->nisn_snapshot ?: '-', $problem];
                }
            }
        }

        return $this->download(new AssessmentAstsWorkbookExport([
            'Ringkasan Status' => $summary,
            'Detail Nilai' => $this->detailSheet($assignments, $data),
            'Belum Lengkap' => $problems,
        ]), "asts-status-pengumpulan-{$assessmentPeriod->getKey()}.xlsx");
    }

    public function homeroom(Request $request, AssessmentPeriod $assessmentPeriod, AssessmentPeriodHomeroom $homeroom): BinaryFileResponse
    {
        $this->ensureAstsAndUser($assessmentPeriod);
        abort_unless((int) $homeroom->assessment_period_id === (int) $assessmentPeriod->getKey() && $this->canViewHomeroom($request->user(), $homeroom), 403);
        $assignments = AssessmentPeriodAssignment::query()->where('assessment_period_id', $assessmentPeriod->getKey())->where('assessment_period_rombel_id', $homeroom->assessment_period_rombel_id)->orderBy('subject_name_snapshot')->get();
        $data = $this->assessmentData($assessmentPeriod, $assignments);
        $ranking = app(AssessmentAstsHomeroomRanking::class)->forClass((int) $assessmentPeriod->getKey(), (int) $homeroom->assessment_period_rombel_id);
        $recap = [['Ranking', 'Nama Siswa', 'NIS/NISN', 'Jumlah Nilai Akhir', 'Rata-rata', 'Status Ranking', 'Mapel Lengkap', 'Mapel Belum Lengkap']];
        $wide = [['Nama Siswa', 'NIS/NISN', ...array_values($ranking['subjects']), 'Jumlah', 'Rata-rata', 'Ranking']];
        foreach ($ranking['rows'] as $row) {
            $recap[] = [$row['rank'] ?: '-', $row['student_name'], $row['nis'], $row['total'], $row['average'], $row['rank'] ? 'Diranking' : 'Belum cukup nilai', $row['completed'], $row['expected'] - $row['completed']];
            $wide[] = [$row['student_name'], $row['nis'], ...array_map(fn ($score) => $score ?? '-', $row['scores']), $row['total'], $row['average'] ?? '-', $row['rank'] ?? '-'];
        }
        $reports = HomeroomReport::query()->where('assessment_period_id', $assessmentPeriod->getKey())->whereIn('assessment_period_student_id', $data['students']->flatten()->pluck('id')->all())->get()->keyBy('assessment_period_student_id');
        $attachments = [['Nama Siswa', 'Sakit', 'Izin', 'Tanpa Keterangan', 'Ekstrakurikuler', 'Predikat A/B/C/D']];
        foreach ($data['students']->flatten() as $student) {
            $report = $reports->get($student->getKey());
            $items = collect($report?->extracurricular_data ?: []);
            $attachments[] = [$student->student_name_snapshot, $report?->sick_days ?? 0, $report?->permission_days ?? 0, $report?->absent_days ?? 0, $items->pluck('name')->filter()->implode(', '), $items->pluck('description')->filter()->implode(', ')];
        }
        return $this->download(new AssessmentAstsWorkbookExport([
            'Rekap Kelas' => $recap,
            'Nilai Akhir Mapel' => $wide,
            'Detail Komponen' => $this->detailSheet($assignments, $data),
            'Lampiran ASTS' => $attachments,
        ]), "asts-rekap-wali-{$assessmentPeriod->getKey()}-{$homeroom->getKey()}.xlsx");
    }

    private function ensureAstsAndUser(AssessmentPeriod $period): void
    {
        abort_unless($period->type === AssessmentType::ASTS && auth()->user() instanceof User, 404);
    }

    private function download(AssessmentAstsWorkbookExport $export, string $filename): BinaryFileResponse
    {
        $temporaryPath = storage_path('app/assessment-exports/tmp');

        File::ensureDirectoryExists($temporaryPath);
        config()->set('excel.temporary_files.local_path', $temporaryPath);

        return Excel::download($export, $filename);
    }

    private function canViewHomeroom(User $user, AssessmentPeriodHomeroom $homeroom): bool
    {
        return app(AssessmentStatusScope::class)->canViewAll($user) || ((int) $user->guru_tendik_id === (int) $homeroom->teacher_id);
    }

    /** @return array{students: \Illuminate\Support\Collection, results: \Illuminate\Support\Collection, scores: \Illuminate\Support\Collection} */
    private function assessmentData(AssessmentPeriod $period, $assignments): array
    {
        $rombelIds = $assignments->pluck('assessment_period_rombel_id')->unique();
        $students = $period->students()->whereIn('assessment_period_rombel_id', $rombelIds)->where('is_active', true)->orderBy('student_name_snapshot')->get()->groupBy('assessment_period_rombel_id');
        $ids = $assignments->modelKeys();
        return [
            'students' => $students,
            'results' => StudentSubjectResult::query()->whereIn('assessment_period_assignment_id', $ids)->get(),
            'scores' => AssessmentScore::query()->whereIn('assessment_period_assignment_id', $ids)->with(['component:id,code', 'updatedBy:id,name'])->get()->groupBy(fn (AssessmentScore $score) => $score->assessment_period_assignment_id.'-'.$score->assessment_period_student_id),
        ];
    }

    private function detailSheet($assignments, array $data): array
    {
        $rows = [['Kelas', 'Mapel', 'Guru', 'Nama Siswa', 'NIS/NISN', 'UH1', 'UH2', 'UH3', 'Rata-rata UH', 'Nilai Murni ASTS', 'Nilai Akhir ASTS', 'Predikat', 'Status Kelengkapan', 'Diperbarui Oleh']];
        foreach ($assignments as $assignment) {
            $results = $data['results']->where('assessment_period_assignment_id', $assignment->getKey())->keyBy('assessment_period_student_id');
            foreach ($data['students']->get($assignment->assessment_period_rombel_id, collect()) as $student) $rows[] = $this->detailRow($assignment, $student, $results->get($student->getKey()), $data['scores']);
        }
        return $rows;
    }

    private function detailRow($assignment, $student, ?StudentSubjectResult $result, $scores): array
    {
        $scores = $scores->get($assignment->getKey().'-'.$student->getKey(), collect());
        $byCode = $scores->keyBy(fn (AssessmentScore $score) => strtoupper((string) $score->component?->code));
        $uh = collect(['UH1', 'UH2', 'UH3'])->map(fn (string $code) => $byCode->get($code)?->score)->all();
        $pure = $byCode->get('ASTS_MURNI')?->score;
        $updaters = $scores->map(fn (AssessmentScore $score) => $this->updaterLabel($score))->filter()->unique()->implode(', ');
        $dailyScores = collect($uh)->filter(fn ($value) => $value !== null);
        return [$assignment->rombel_name_snapshot, $assignment->subject_name_snapshot, $assignment->teacher_name_snapshot, $student->student_name_snapshot, $student->nis_snapshot ?: $student->nisn_snapshot ?: '-', ...$uh, $dailyScores->isNotEmpty() ? $dailyScores->avg() : '-', $pure ?? '-', $result?->final_score ?? '-', $result?->predicate ?? '-', $result?->final_score !== null ? 'Lengkap' : 'Belum lengkap', $updaters ?: '-'];
    }

    private function updaterLabel(AssessmentScore $score): ?string
    {
        $user = $score->updatedBy;
        if (! $user) {
            return null;
        }

        $badge = $user->hasRole('admin') ? 'Admin' : ($user->hasRole('kurikulum') ? 'Kurikulum' : null);

        return $user->name.($badge ? " ({$badge})" : '');
    }

    private function problems($assignment, array $row): array
    {
        $problems = [];
        if (collect(array_slice($row, 5, 3))->every(fn ($score) => $score === null || $score === '')) $problems[] = 'Nilai UH minimal belum ada';
        if ($row[9] === '-') $problems[] = 'Nilai Murni ASTS kosong';
        if ($row[10] === '-') $problems[] = 'Siswa belum lengkap';
        return $problems;
    }
}
