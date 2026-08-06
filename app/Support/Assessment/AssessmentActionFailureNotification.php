<?php

namespace App\Support\Assessment;

use App\Enums\Assessment\AssessmentType;
use App\Filament\Pages\Assessment\AsasHomeroomRecap;
use App\Filament\Pages\Assessment\AsasHub;
use App\Filament\Pages\Assessment\AsasInputScores;
use App\Filament\Pages\Assessment\AsasReports;
use App\Filament\Pages\Assessment\AsasSubmissionStatus;
use App\Filament\Pages\Assessment\AssessmentDashboard;
use App\Filament\Pages\Assessment\AstsHomeroomRecap;
use App\Filament\Pages\Assessment\AstsHub;
use App\Filament\Pages\Assessment\AstsInputScores;
use App\Filament\Pages\Assessment\AstsReports;
use App\Filament\Pages\Assessment\AstsSubmissionStatus;
use App\Filament\Resources\AssessmentReportTemplateResource;
use App\Models\Assessment\AssessmentPeriod;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class AssessmentActionFailureNotification
{
    public static function send(
        Throwable $exception,
        string $actionTitle,
        ?AssessmentPeriod $period = null,
    ): Notification {
        return self::make($exception, $actionTitle, $period)->send();
    }

    public static function make(
        Throwable $exception,
        string $actionTitle,
        ?AssessmentPeriod $period = null,
    ): Notification {
        $detail = self::safeDetail($exception);
        $repair = self::repairAction($exception, $detail, $period);

        return Notification::make()
            ->title('Aksi ditolak: '.$actionTitle)
            ->body("**Kendala:** {$detail}\n\n**Solusi:** {$repair['solution']}")
            ->danger()
            ->persistent()
            ->actions([
                Action::make('openAssessmentRepair')
                    ->label($repair['label'])
                    ->icon($repair['icon'])
                    ->url($repair['url']),
            ]);
    }

    /**
     * @return array{label:string,url:string,icon:string,solution:string}
     */
    private static function repairAction(
        Throwable $exception,
        string $detail,
        ?AssessmentPeriod $period,
    ): array {
        $keys = $exception instanceof ValidationException
            ? implode(' ', array_keys($exception->errors()))
            : '';
        $context = Str::lower($keys.' '.$detail);
        $periodId = $period?->getKey();
        $type = $period?->type instanceof AssessmentType
            ? $period->type
            : AssessmentType::tryFrom((string) $period?->type);

        if ($period && self::containsAny($context, [
            'assignment', 'assignments', 'penugasan', 'dikirim', 'verifikasi',
        ])) {
            $page = $type === AssessmentType::ASAS ? AsasSubmissionStatus::class : AstsSubmissionStatus::class;
            if ($page::canAccess()) {
                return [
                    'label' => 'Buka Status Pengumpulan',
                    'url' => $page::getUrl(['period' => $periodId]),
                    'icon' => 'heroicon-o-clipboard-document-check',
                    'solution' => 'Periksa penugasan yang belum dikirim atau belum diverifikasi, selesaikan kendalanya, lalu jalankan aksi kembali.',
                ];
            }
        }

        if ($period && self::containsAny($context, [
            'result', 'results', 'score', 'scores', 'nilai', 'capaian',
        ])) {
            $page = $type === AssessmentType::ASAS ? AsasInputScores::class : AstsInputScores::class;
            if ($page::canAccess()) {
                return [
                    'label' => 'Buka Input Nilai',
                    'url' => $page::getUrl(['period' => $periodId]),
                    'icon' => 'heroicon-o-pencil-square',
                    'solution' => 'Lengkapi atau koreksi nilai pada periode ini, simpan perubahan, lalu ulangi aksi.',
                ];
            }
        }

        if ($period && self::containsAny($context, [
            'homeroom', 'wali', 'sikap', 'spiritual', 'sosial', 'ekstrakurikuler', 'prestasi', 'semester_status',
        ])) {
            $page = $type === AssessmentType::ASAS ? AsasHomeroomRecap::class : AstsHomeroomRecap::class;
            if ($page::canAccess()) {
                return [
                    'label' => 'Buka Rekap Wali Kelas',
                    'url' => $page::getUrl(['period' => $periodId]),
                    'icon' => 'heroicon-o-user-group',
                    'solution' => 'Lengkapi rekap wali kelas yang disebutkan, simpan, lalu jalankan aksi kembali.',
                ];
            }
        }

        if (self::containsAny($context, ['template', 'identitas rapor'])
            && AssessmentReportTemplateResource::canViewAny()) {
            return [
                'label' => 'Buka Template Rapor',
                'url' => AssessmentReportTemplateResource::getUrl(),
                'icon' => 'heroicon-o-document-text',
                'solution' => 'Periksa template utama dan identitas rapor, simpan perbaikannya, lalu ulangi aksi.',
            ];
        }

        if ($period && self::containsAny($context, [
            'report', 'reports', 'rapor', 'pdf', 'snapshot', 'revisi', 'cache', 'antrean',
        ])) {
            $page = $type === AssessmentType::ASAS ? AsasReports::class : AstsReports::class;
            if ($page::canAccess()) {
                return [
                    'label' => 'Buka Proses Rapor',
                    'url' => $page::getUrl(['period' => $periodId]),
                    'icon' => 'heroicon-o-printer',
                    'solution' => 'Periksa kelengkapan, status revisi, dan antrean PDF pada periode ini sebelum mencoba kembali.',
                ];
            }
        }

        if ($period) {
            $page = $type === AssessmentType::ASAS ? AsasHub::class : AstsHub::class;
            if ($page::canAccess()) {
                return [
                    'label' => 'Buka Pusat '.$type?->label(),
                    'url' => $page::getUrl(['period' => $periodId]),
                    'icon' => 'heroicon-o-arrow-top-right-on-square',
                    'solution' => 'Buka pusat periode ini untuk memeriksa data dan akses yang masih perlu dilengkapi.',
                ];
            }
        }

        if (AssessmentDashboard::canAccess()) {
            return [
                'label' => 'Buka Pengaturan Penilaian',
                'url' => AssessmentDashboard::getUrl($periodId ? ['period' => $periodId] : []),
                'icon' => 'heroicon-o-wrench-screwdriver',
                'solution' => 'Periksa kelengkapan dan hak akses pada Pengaturan Penilaian, lalu ulangi aksi.',
            ];
        }

        return [
            'label' => 'Tinjau Halaman Ini',
            'url' => url()->current(),
            'icon' => 'heroicon-o-eye',
            'solution' => 'Periksa kembali isian pada halaman ini. Jika akses perbaikan tidak tersedia, hubungi admin atau kurikulum.',
        ];
    }

    private static function safeDetail(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            $message = collect($exception->errors())->flatten()->first();
        } elseif ($exception instanceof QueryException) {
            $message = null;
        } else {
            $message = $exception->getMessage();
        }

        $message = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $message)) ?? '');

        return $message !== ''
            ? Str::limit($message, 800)
            : 'Sistem belum dapat menyelesaikan aksi ini. Detail teknis sudah dicatat untuk pemeriksaan.';
    }

    /** @param array<int, string> $needles */
    private static function containsAny(string $haystack, array $needles): bool
    {
        return collect($needles)->contains(fn (string $needle): bool => str_contains($haystack, $needle));
    }
}
