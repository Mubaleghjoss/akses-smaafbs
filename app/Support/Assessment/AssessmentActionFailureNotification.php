<?php

namespace App\Support\Assessment;

use App\Filament\Pages\Assessment\AssessmentDashboard;
use App\Filament\Resources\AssessmentReportTemplateResource;
use App\Filament\Resources\AssessmentSchemeResource;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
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
        ?AssessmentPeriodAssignment $blockingAssignment = null,
    ): Notification {
        return self::make($exception, $actionTitle, $period, $blockingAssignment)->send();
    }

    public static function make(
        Throwable $exception,
        string $actionTitle,
        ?AssessmentPeriod $period = null,
        ?AssessmentPeriodAssignment $blockingAssignment = null,
    ): Notification {
        $detail = self::safeDetail($exception);
        $repair = self::repairAction($exception, $detail, $actionTitle, $period, $blockingAssignment);

        return Notification::make()
            ->title('Aksi ditolak: '.$actionTitle)
            ->body("Kendala: {$detail}\n\nSolusi: {$repair['solution']}")
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
        string $actionTitle,
        ?AssessmentPeriod $period,
        ?AssessmentPeriodAssignment $blockingAssignment,
    ): array {
        $keys = $exception instanceof ValidationException
            ? implode(' ', array_keys($exception->errors()))
            : '';
        $context = Str::lower($keys.' '.$detail.' '.$actionTitle);
        $periodId = $period?->getKey();
        $type = AssessmentPageMap::normalizeType($period?->type);

        // Authorization failures need an access-specific destination, rather
        // than being mistaken for a missing report-data condition.
        if ($period && $exception instanceof AuthorizationException) {
            $page = AssessmentPageMap::page($type, 'reports');

            if ($page::canAccess()) {
                return [
                    'label' => 'Cek Hak Akses Cetak Rapor',
                    'url' => $page::getUrl(['period' => $periodId]),
                    'icon' => 'heroicon-o-lock-closed',
                    'solution' => 'Anda belum memiliki hak untuk mengubah revisi atau antrean PDF. Buka cetak rapor periode ini untuk memeriksa proses yang tersedia, atau minta admin/kurikulum memberikan hak akses cetak rapor.',
                ];
            }
        }

        if (self::containsAny($context, [
            'scheme', 'skema', 'komponen', 'bobot',
        ]) && AssessmentSchemeResource::canViewAny()) {
            return [
                'label' => 'Buka Komponen dan Bobot',
                'url' => AssessmentSchemeResource::getUrl('index', $periodId ? ['tableFilters' => [
                    'assessment_period_id' => ['value' => $periodId],
                ]] : []),
                'icon' => 'heroicon-o-adjustments-horizontal',
                'solution' => 'Pilih atau lengkapi skema aktif beserta komponen dengan total bobot 100%, lalu jalankan sinkronisasi kembali.',
            ];
        }

        if ($period && ! self::containsAny(Str::lower($actionTitle), ['simpan draf nilai', 'input nilai']) && self::containsAny($context, [
            'assignment', 'assignments', 'penugasan', 'dikirim', 'verifikasi',
        ])) {
            $page = AssessmentPageMap::page($type, 'status');

            if ($blockingAssignment) {
                $status = $blockingAssignment->status instanceof \BackedEnum
                    ? $blockingAssignment->status->value
                    : (string) $blockingAssignment->status;
                $statusLabel = $status === 'draft' ? 'belum dikirim' : 'belum valid';
                $rombel = (string) $blockingAssignment->rombel_name_snapshot;
                $subject = (string) $blockingAssignment->subject_name_snapshot;

                return [
                    'label' => "Lihat {$rombel} · {$subject} yang {$statusLabel}",
                    'url' => $page::getUrl([
                        'period' => $periodId,
                        'rombel' => $blockingAssignment->assessment_period_rombel_id,
                        'subject' => $blockingAssignment->assessment_subject_id,
                        'status' => $status,
                    ]),
                    'icon' => 'heroicon-o-clipboard-document-check',
                    'solution' => 'Buka penugasan yang menghambat, perbaiki atau kirimkan, lalu jalankan aksi kembali.',
                ];
            }

            return [
                'label' => 'Buka Status Pengumpulan',
                'url' => $page::getUrl(['period' => $periodId]),
                'icon' => 'heroicon-o-clipboard-document-check',
                'solution' => 'Periksa penugasan yang belum dikirim atau belum diverifikasi, selesaikan kendalanya, lalu jalankan aksi kembali.',
            ];
        }

        if ($period && (
            $blockingAssignment
            || self::containsAny($context, [
                'result', 'results', 'score', 'scores', 'nilai', 'capaian',
            ])
        )) {
            $page = AssessmentPageMap::page($type, 'input');

            return [
                'label' => 'Buka Input Nilai',
                'url' => $page::getUrl([
                    'period' => $periodId,
                    ...($blockingAssignment ? ['assignment' => $blockingAssignment->getKey()] : []),
                ]),
                'icon' => 'heroicon-o-pencil-square',
                'solution' => $blockingAssignment
                    ? 'Buka penugasan nilai terkait, koreksi nilainya, simpan perubahan, lalu ulangi aksi.'
                    : 'Lengkapi atau koreksi nilai pada periode ini, simpan perubahan, lalu ulangi aksi.',
            ];
        }

        if ($period && self::containsAny($context, [
            'homeroom', 'wali', 'sikap', 'spiritual', 'sosial', 'ekstrakurikuler', 'prestasi', 'semester_status',
        ])) {
            $page = AssessmentPageMap::page($type, 'recap');
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
            'report', 'reports', 'rapor', 'pdf', 'snapshot', 'revisi', 'cache', 'antrean', 'zip', 'export', 'download',
        ])) {
            $page = AssessmentPageMap::page($type, 'reports');
            if ($page::canAccess()) {
                return [
                    'label' => 'Buka Cetak Rapor Periode Ini',
                    'url' => $page::getUrl(['period' => $periodId]),
                    'icon' => 'heroicon-o-printer',
                    'solution' => 'Buka Cetak Rapor pada periode ini, pilih kelas terkait, lalu coba kembali export ZIP atau pratinjaunya.',
                ];
            }
        }

        if ($period) {
            $page = AssessmentPageMap::page($type, 'hub');
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
