<?php

namespace App\Support\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\HomeroomReport;
use App\Models\Assessment\ReportTemplate;
use App\Models\User;
use App\Support\Assessment\Reporting\AssessmentReportPreflight;
use Illuminate\Support\Collection;

final class AssessmentReportProgress
{
    /** @return Collection<int, array<string, mixed>> */
    public function forUser(User $user): Collection
    {
        return AssessmentPeriod::query()
            ->whereIn('status', $this->operationalStatuses())
            ->latest('id')
            ->get()
            ->map(fn (AssessmentPeriod $period): array => $this->periodProgress($period, $user))
            ->filter(fn (array $period): bool => $period['roles'] !== [])
            ->values();
    }

    /** @return array{key: string, title: string, description: string, action: string, icon: string} */
    public function emptyStateForUser(User $user): array
    {
        $operationalPeriods = AssessmentPeriod::query()
            ->whereIn('status', $this->operationalStatuses())
            ->latest('id')
            ->get(['name']);

        if ($operationalPeriods->isNotEmpty()) {
            $names = $operationalPeriods->pluck('name')->filter()->implode(', ');

            return [
                'key' => 'unassigned',
                'title' => 'Belum Ada Tanggung Jawab Rapor',
                'description' => "Periode {$names} sedang aktif, tetapi akun Anda belum tercatat sebagai Guru mapel, Wali Kelas, Kurikulum, atau Admin pada periode tersebut.",
                'action' => 'Jika Anda seharusnya memiliki tugas, hubungi Kurikulum atau Admin agar penugasan dan akses akun diperiksa.',
                'icon' => 'heroicon-o-user-plus',
            ];
        }

        $draft = AssessmentPeriod::query()
            ->where('status', AssessmentPeriodStatus::DRAFT->value)
            ->latest('id')
            ->first(['name', 'entry_start_at']);

        if ($draft) {
            $schedule = $draft->entry_start_at
                ? ' Jadwal mulai input: '.$draft->entry_start_at->translatedFormat('d F Y, H:i').'.'
                : '';

            return [
                'key' => 'not_started',
                'title' => 'Progres Rapor Belum Dibuka',
                'description' => "Periode {$draft->name} masih disiapkan dan belum memasuki waktu pengerjaan.{$schedule}",
                'action' => 'Anda belum perlu mengerjakan apa pun. Halaman ini akan menampilkan tugas setelah periode dibuka dan penugasan selesai disusun.',
                'icon' => 'heroicon-o-clock',
            ];
        }

        return [
            'key' => 'no_period',
            'title' => 'Belum Ada Periode Rapor Aktif',
            'description' => 'Saat ini tidak ada periode penilaian yang sedang dikerjakan dalam antrean progres rapor.',
            'action' => 'Tidak ada tindakan yang perlu dilakukan. Periksa kembali setelah Kurikulum membuka periode penilaian berikutnya.',
            'icon' => 'heroicon-o-calendar-days',
        ];
    }

    /** @return array<string, mixed> */
    private function periodProgress(AssessmentPeriod $period, User $user): array
    {
        $type = AssessmentPageMap::normalizeType($period->type);
        $roles = collect();

        if ($user->guru_tendik_id) {
            $teacherAssignments = $period->assignments()
                ->where('teacher_id', $user->guru_tendik_id)
                ->get(['id', 'status']);

            if ($teacherAssignments->isNotEmpty()) {
                $completed = $teacherAssignments->filter(fn ($assignment): bool => in_array(
                    $this->statusValue($assignment->status),
                    [AssignmentStatus::SUBMITTED->value, AssignmentStatus::VERIFIED->value, AssignmentStatus::LOCKED->value],
                    true,
                ))->count();
                $returned = $teacherAssignments->filter(fn ($assignment): bool => $this->statusValue($assignment->status) === AssignmentStatus::RETURNED->value)->count();
                $total = $teacherAssignments->count();
                $roles->push([
                    'key' => 'teacher',
                    'label' => 'Sebagai Guru',
                    'scope' => $total.' penugasan mapel',
                    'total' => $total,
                    'completed' => $completed,
                    'pending' => $total - $completed,
                    'percent' => $this->percent($completed, $total),
                    'status' => $completed === $total ? 'Selesai' : ($returned > 0 ? 'Perlu Perbaikan' : 'Perlu Tindakan'),
                    'next_action' => $completed === $total ? 'Pantau sampai nilai dikunci' : ($returned > 0 ? 'Perbaiki nilai yang dikembalikan' : 'Lengkapi dan kirim nilai'),
                    'url' => AssessmentPageMap::page($type, 'input')::getUrl(['period' => $period->getKey()]),
                ]);
            }

            $homerooms = $period->homerooms()
                ->where('teacher_id', $user->guru_tendik_id)
                ->get(['assessment_period_rombel_id', 'rombel_name_snapshot']);

            if ($homerooms->isNotEmpty()) {
                $rombelIds = $homerooms->pluck('assessment_period_rombel_id');
                $studentIds = $period->students()
                    ->where('is_active', true)
                    ->whereIn('assessment_period_rombel_id', $rombelIds)
                    ->pluck('id');
                $total = $studentIds->count();
                $completed = HomeroomReport::query()
                    ->where('assessment_period_id', $period->getKey())
                    ->whereIn('assessment_period_student_id', $studentIds)
                    ->whereNotNull('homeroom_note')
                    ->count();
                $roles->push([
                    'key' => 'homeroom',
                    'label' => 'Sebagai Wali Kelas',
                    'scope' => $homerooms->pluck('rombel_name_snapshot')->implode(', '),
                    'total' => $total,
                    'completed' => $completed,
                    'pending' => max(0, $total - $completed),
                    'percent' => $this->percent($completed, $total),
                    'status' => $total > 0 && $completed === $total ? 'Selesai' : 'Perlu Tindakan',
                    'next_action' => $total > 0 && $completed === $total ? 'Pantau hasil preflight rapor' : 'Lengkapi rekap wali kelas',
                    'url' => AssessmentPageMap::page($type, 'recap')::getUrl(['period' => $period->getKey()]),
                ]);
            }
        }

        if ($user->hasRole('kurikulum') || $user->hasRole('kepala_sekolah') || $user->can('penilaian.verify')) {
            $assignments = $period->assignments()->get(['status']);
            $total = $assignments->count();
            $completed = $assignments->filter(fn ($assignment): bool => in_array(
                $this->statusValue($assignment->status),
                [AssignmentStatus::VERIFIED->value, AssignmentStatus::LOCKED->value],
                true,
            ))->count();
            $waitingVerification = $assignments->filter(fn ($assignment): bool => $this->statusValue($assignment->status) === AssignmentStatus::SUBMITTED->value)->count();
            $returned = $assignments->filter(fn ($assignment): bool => $this->statusValue($assignment->status) === AssignmentStatus::RETURNED->value)->count();
            $roles->push([
                'key' => 'curriculum',
                'label' => 'Sebagai Kurikulum',
                'scope' => 'Seluruh penugasan pada periode ini',
                'total' => $total,
                'completed' => $completed,
                'pending' => max(0, $total - $completed),
                'waiting_verification' => $waitingVerification,
                'percent' => $this->percent($completed, $total),
                'status' => $total > 0 && $completed === $total ? 'Selesai' : 'Perlu Tindakan',
                'next_action' => $waitingVerification > 0
                    ? "Verifikasi {$waitingVerification} penugasan yang sudah dikirim"
                    : ($returned > 0 ? 'Pantau perbaikan nilai yang dikembalikan' : 'Pantau kelengkapan pengumpulan nilai'),
                'url' => AssessmentPageMap::page($type, 'status')::getUrl(['period' => $period->getKey()]),
            ]);
        }

        $readyToPrint = false;
        $readinessIssues = [];
        if ($user->hasFullAdminAccess()) {
            $template = $this->reportTemplate($period);
            if ($template) {
                $preflight = app(AssessmentReportPreflight::class)->inspect($period, $template);
                $readyToPrint = (bool) $preflight['ready'];
                $readinessIssues = collect($preflight['groups'])
                    ->flatMap(fn (array $group): array => $group['issues'])
                    ->values()
                    ->all();
            }

            $roles->push([
                'key' => 'admin',
                'label' => 'Sebagai Admin',
                'scope' => 'Konfigurasi, preflight, dan kesiapan cetak',
                'total' => max(1, count($readinessIssues)),
                'completed' => $readyToPrint ? max(1, count($readinessIssues)) : 0,
                'pending' => $readyToPrint ? 0 : max(1, count($readinessIssues)),
                'percent' => $readyToPrint ? 100 : 0,
                'status' => $readyToPrint ? 'Siap Cetak' : 'Perlu Tindakan',
                'next_action' => ! $template
                    ? 'Lengkapi dan aktifkan template rapor'
                    : ($readyToPrint ? 'Rapor siap dicetak' : ($readinessIssues[0]['message'] ?? 'Periksa hambatan preflight rapor')),
                'url' => AssessmentPageMap::page($type, 'reports')::getUrl(['period' => $period->getKey()]),
                'issues' => $readinessIssues,
            ]);
        }

        $overallPercent = $roles->isNotEmpty()
            ? (int) round($roles->avg(fn (array $role): int => (int) $role['percent']))
            : 0;

        return [
            'id' => (int) $period->getKey(),
            'code' => (string) $period->code,
            'name' => (string) $period->name,
            'type' => $type?->value,
            'type_label' => $type?->label() ?? strtoupper((string) $period->type),
            'status' => $period->status instanceof AssessmentPeriodStatus ? $period->status->value : (string) $period->status,
            'status_label' => $period->status instanceof AssessmentPeriodStatus ? $period->status->label() : (string) $period->status,
            'ready_to_print' => $readyToPrint,
            'readiness_label' => $readyToPrint ? 'Siap Cetak' : 'Belum Siap Cetak',
            'overall_percent' => $overallPercent,
            'roles' => $roles->values()->all(),
        ];
    }

    private function reportTemplate(AssessmentPeriod $period): ?ReportTemplate
    {
        $type = AssessmentPageMap::normalizeType($period->type);
        if (! $type) {
            return null;
        }

        $candidates = $type->templateTypeCandidates();
        $priority = array_flip($candidates);

        return ReportTemplate::query()
            ->whereIn('type', $candidates)
            ->orderByDesc('is_active')
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->get()
            ->sortBy(function (ReportTemplate $template) use ($priority): int {
                $templateType = $template->type instanceof AssessmentType
                    ? $template->type->value
                    : (string) $template->type;

                return $priority[$templateType] ?? 99;
            })
            ->first();
    }

    /** @return list<string> */
    private function operationalStatuses(): array
    {
        return [
            AssessmentPeriodStatus::OPEN->value,
            AssessmentPeriodStatus::ENTRY_CLOSED->value,
            AssessmentPeriodStatus::VERIFICATION->value,
            AssessmentPeriodStatus::LOCKED->value,
        ];
    }

    private function statusValue(mixed $status): string
    {
        return $status instanceof AssignmentStatus ? $status->value : (string) $status;
    }

    private function percent(int $completed, int $total): int
    {
        return $total > 0 ? (int) round(($completed / $total) * 100) : 0;
    }
}
