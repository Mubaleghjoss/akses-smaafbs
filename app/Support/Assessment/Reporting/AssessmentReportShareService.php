<?php

namespace App\Support\Assessment\Reporting;

use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\AuditLog;
use App\Models\Assessment\ReportShareLink;
use App\Models\Assessment\ReportSnapshot;
use App\Models\Assessment\ReportTemplate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AssessmentReportShareService
{
    public const ALLOWED_EXPIRY_DAYS = [1, 3, 7];

    public function __construct(
        private readonly AssessmentReportStorage $storage,
        private readonly AssessmentSnapshotIntegrity $integrity,
        private readonly BuildAssessmentReportPreviewSnapshot $previewBuilder,
    ) {}

    /**
     * @return array{link:ReportShareLink,token:string}
     */
    public static function defaultExpiryDays(): int
    {
        $hours = (int) config('assessment.share_links.default_expiry_hours', 24);
        $days = $hours > 0 && $hours % 24 === 0 ? intdiv($hours, 24) : 1;

        return in_array($days, self::ALLOWED_EXPIRY_DAYS, true) ? $days : 1;
    }

    public function issue(ReportSnapshot $snapshot, int $createdBy, ?int $expiryDays = null): array
    {
        $expiryDays ??= self::defaultExpiryDays();

        if (! in_array($expiryDays, self::ALLOWED_EXPIRY_DAYS, true)) {
            throw new UnprocessableEntityHttpException('Masa berlaku tautan harus 1, 3, atau 7 hari.');
        }

        $actor = User::query()->findOrFail($createdBy);
        Gate::forUser($actor)->authorize('create', ReportShareLink::class);
        Gate::forUser($actor)->authorize('view', $snapshot);

        return DB::transaction(function () use ($snapshot, $createdBy, $expiryDays): array {
            $snapshot = ReportSnapshot::query()->lockForUpdate()->findOrFail($snapshot->getKey());
            $this->assertDownloadable($snapshot);
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

            $link = ReportShareLink::query()->create([
                'assessment_report_snapshot_id' => $snapshot->getKey(),
                'token_hash' => hash('sha256', $token),
                'expires_at' => Carbon::now()->addDays($expiryDays),
                'revoked_at' => null,
                'created_by' => $createdBy,
                'last_accessed_at' => null,
                'download_count' => 0,
            ]);

            AuditLog::query()->create([
                'assessment_period_id' => $snapshot->assessment_period_id,
                'actor_id' => $createdBy,
                'event' => 'report_share_link_created',
                'subject_type' => ReportShareLink::class,
                'subject_id' => $link->getKey(),
                'old_values' => null,
                'new_values' => [
                    'report_snapshot_id' => $snapshot->getKey(),
                    'expires_at' => $link->expires_at?->toIso8601String(),
                ],
                'reason' => null,
                'ip_address' => request()?->ip(),
                'user_agent' => $this->limitedUserAgent(request()?->userAgent()),
                'created_at' => Carbon::now(),
            ]);

            return compact('link', 'token');
        }, 3);
    }

    /**
     * Persist a stream-only snapshot when a live report is shared before a
     * formal generation revision exists; no PDF file is stored.
     *
     * @return array{link:ReportShareLink,token:string}
     */
    public function issueLivePreview(
        AssessmentPeriod $period,
        ReportTemplate $template,
        AssessmentPeriodStudent $student,
        int $createdBy,
        ?int $expiryDays = null,
    ): array {
        $actor = User::query()->findOrFail($createdBy);
        Gate::forUser($actor)->authorize('create', ReportShareLink::class);
        Gate::forUser($actor)->authorize('view', $period);
        Gate::forUser($actor)->authorize('view', $template);
        Gate::forUser($actor)->authorize('view', $student);
        abort_unless((int) $student->assessment_period_id === (int) $period->getKey(), 404);

        $snapshot = DB::transaction(function () use ($period, $template, $student, $createdBy): ReportSnapshot {
            $existing = ReportSnapshot::query()
                ->where('assessment_period_id', $period->getKey())
                ->where('assessment_period_student_id', $student->getKey())
                ->where('assessment_report_template_id', $template->getKey())
                ->where('revision', 0)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $preview = $this->previewBuilder->build($period, $template, $student);
            $data = $preview->snapshot_data;

            return ReportSnapshot::query()->create([
                'assessment_period_id' => $period->getKey(),
                'assessment_period_student_id' => $student->getKey(),
                'assessment_report_template_id' => $template->getKey(),
                'revision' => 0,
                'template_version' => $template->version,
                'snapshot_data' => $data,
                'snapshot_checksum' => $this->integrity->checksum($data),
                'generation_status' => 'ready',
                'delivery_mode' => 'stream',
                'generated_by' => $createdBy,
            ]);
        }, 3);

        return $this->issue($snapshot, $createdBy, $expiryDays);
    }

    public function revoke(ReportShareLink $link, int $actorId, ?string $reason = null): void
    {
        $actor = User::query()->findOrFail($actorId);
        Gate::forUser($actor)->authorize('revoke', $link);

        if ($link->revoked_at !== null) {
            return;
        }

        DB::transaction(function () use ($link, $actorId, $reason): void {
            $locked = ReportShareLink::query()->lockForUpdate()->findOrFail($link->getKey());

            if ($locked->revoked_at !== null) {
                return;
            }

            $locked->forceFill(['revoked_at' => Carbon::now()])->save();
            $snapshot = ReportSnapshot::query()->find($locked->assessment_report_snapshot_id);

            AuditLog::query()->create([
                'assessment_period_id' => $snapshot?->assessment_period_id,
                'actor_id' => $actorId,
                'event' => 'report_share_link_revoked',
                'subject_type' => ReportShareLink::class,
                'subject_id' => $locked->getKey(),
                'old_values' => ['revoked_at' => null],
                'new_values' => ['revoked_at' => $locked->revoked_at?->toIso8601String()],
                'reason' => $reason,
                'ip_address' => request()?->ip(),
                'user_agent' => $this->limitedUserAgent(request()?->userAgent()),
                'created_at' => Carbon::now(),
            ]);
        }, 3);
    }

    public function revokeForSnapshot(ReportSnapshot $snapshot, int $actorId, string $reason): int
    {
        $count = 0;

        ReportShareLink::query()
            ->where('assessment_report_snapshot_id', $snapshot->getKey())
            ->whereNull('revoked_at')
            ->each(function (ReportShareLink $link) use ($actorId, $reason, &$count): void {
                $this->revoke($link, $actorId, $reason);
                $count++;
            });

        return $count;
    }

    public function resolve(string $plainToken): ReportShareLink
    {
        if (preg_match('/^[A-Za-z0-9_-]{43}$/', $plainToken) !== 1) {
            throw new NotFoundHttpException('Tautan rapor tidak ditemukan.');
        }

        $link = ReportShareLink::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (! $link) {
            throw new NotFoundHttpException('Tautan rapor tidak ditemukan.');
        }

        if ($link->revoked_at !== null) {
            throw new GoneHttpException('Tautan rapor sudah dicabut.');
        }

        if ($link->expires_at === null || $link->expires_at->isPast()) {
            throw new GoneHttpException('Tautan rapor sudah kedaluwarsa.');
        }

        $snapshot = ReportSnapshot::query()->find($link->assessment_report_snapshot_id);

        if (! $snapshot) {
            throw new GoneHttpException('Rapor pada tautan ini sudah tidak tersedia.');
        }

        $this->assertDownloadable($snapshot);
        $link->setRelation('snapshot', $snapshot);

        return $link;
    }

    public function recordDownload(
        ReportShareLink $link,
        ?string $ipAddress,
        ?string $userAgent,
    ): ReportShareLink {
        return DB::transaction(function () use ($link, $ipAddress, $userAgent): ReportShareLink {
            ReportSnapshot::query()->findOrFail($link->assessment_report_snapshot_id);
            $locked = ReportShareLink::query()->lockForUpdate()->findOrFail($link->getKey());

            if ($locked->revoked_at !== null || $locked->expires_at === null || $locked->expires_at->isPast()) {
                throw new GoneHttpException('Tautan rapor tidak lagi aktif.');
            }

            $locked->forceFill([
                'last_accessed_at' => Carbon::now(),
                'download_count' => (int) $locked->download_count + 1,
            ])->save();

            $snapshot = ReportSnapshot::query()->findOrFail($locked->assessment_report_snapshot_id);
            $this->assertDownloadable($snapshot);

            AuditLog::query()->create([
                'assessment_period_id' => $snapshot->assessment_period_id,
                'actor_id' => null,
                'event' => 'report_downloaded_from_share_link',
                'subject_type' => ReportShareLink::class,
                'subject_id' => $locked->getKey(),
                'old_values' => null,
                'new_values' => [
                    'report_snapshot_id' => $snapshot->getKey(),
                    'download_count' => $locked->download_count,
                ],
                'reason' => null,
                'ip_address' => $ipAddress,
                'user_agent' => $this->limitedUserAgent($userAgent),
                'created_at' => Carbon::now(),
            ]);

            $locked->setRelation('snapshot', $snapshot);

            return $locked;
        }, 3);
    }

    private function assertDownloadable(ReportSnapshot $snapshot): void {
        $status = $snapshot->generation_status;
        $status = $status instanceof \BackedEnum ? $status->value : (string) $status;

        $downloadable = (string) $snapshot->delivery_mode === 'stream'
            ? $status === 'ready' && $this->integrity->isValid($snapshot)
            : $status === 'completed' && $this->storage->isValid($snapshot->pdf_path, $snapshot->checksum);

        if (! $downloadable) {
            throw new GoneHttpException('Rapor belum tersedia atau snapshot tidak valid.');
        }

    }

    private function limitedUserAgent(?string $userAgent): ?string
    {
        $userAgent = trim((string) $userAgent);

        return $userAgent !== '' ? Str::limit($userAgent, 500, '') : null;
    }
}
