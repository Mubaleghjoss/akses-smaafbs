<?php

namespace App\Support\Assessment\Reporting;

use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AuditLog;
use App\Models\Assessment\ReportShareLink;
use App\Models\Assessment\ReportSnapshot;
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

        return DB::transaction(function () use ($snapshot, $createdBy, $expiryDays): array {
            $period = AssessmentPeriod::query()
                ->lockForUpdate()
                ->findOrFail($snapshot->assessment_period_id);
            $snapshot = ReportSnapshot::query()->lockForUpdate()->findOrFail($snapshot->getKey());
            $this->assertPublishedAndDownloadable($snapshot, $period);
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

        $this->assertPublishedAndDownloadable($snapshot);
        $link->setRelation('snapshot', $snapshot);

        return $link;
    }

    public function recordDownload(
        ReportShareLink $link,
        ?string $ipAddress,
        ?string $userAgent,
    ): ReportShareLink {
        return DB::transaction(function () use ($link, $ipAddress, $userAgent): ReportShareLink {
            $initialSnapshot = ReportSnapshot::query()->findOrFail($link->assessment_report_snapshot_id);
            $period = AssessmentPeriod::query()
                ->lockForUpdate()
                ->findOrFail($initialSnapshot->assessment_period_id);
            $locked = ReportShareLink::query()->lockForUpdate()->findOrFail($link->getKey());

            if ($locked->revoked_at !== null || $locked->expires_at === null || $locked->expires_at->isPast()) {
                throw new GoneHttpException('Tautan rapor tidak lagi aktif.');
            }

            $locked->forceFill([
                'last_accessed_at' => Carbon::now(),
                'download_count' => (int) $locked->download_count + 1,
            ])->save();

            $snapshot = ReportSnapshot::query()->findOrFail($locked->assessment_report_snapshot_id);
            $this->assertPublishedAndDownloadable($snapshot, $period);

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

    private function assertPublishedAndDownloadable(
        ReportSnapshot $snapshot,
        ?AssessmentPeriod $period = null,
    ): void {
        $status = $snapshot->generation_status;
        $status = $status instanceof \BackedEnum ? $status->value : (string) $status;

        $downloadable = (string) $snapshot->delivery_mode === 'stream'
            ? $status === 'ready' && $this->integrity->isValid($snapshot)
            : $status === 'completed' && $this->storage->isValid($snapshot->pdf_path, $snapshot->checksum);

        if (! $downloadable) {
            throw new GoneHttpException('Rapor belum tersedia atau snapshot tidak valid.');
        }

        $periodStatus = $period?->status
            ?? AssessmentPeriod::query()->whereKey($snapshot->assessment_period_id)->value('status');
        $periodStatus = $periodStatus instanceof \BackedEnum ? $periodStatus->value : (string) $periodStatus;

        if ($periodStatus !== 'published') {
            throw new GoneHttpException('Rapor belum dipublikasikan.');
        }

        $period ??= AssessmentPeriod::query()->find($snapshot->assessment_period_id);
        $settings = is_array($period?->settings) ? $period->settings : [];
        $publishedTemplateId = (int) data_get($settings, '_reporting.published.template_id');
        $publishedRevision = (int) data_get($settings, '_reporting.published.revision');
        $latestRevision = (int) ReportSnapshot::query()
            ->where('assessment_period_id', $snapshot->assessment_period_id)
            ->where('assessment_report_template_id', $snapshot->assessment_report_template_id)
            ->max('revision');

        if (
            $publishedTemplateId < 1
            || $publishedRevision < 1
            || (int) $snapshot->assessment_report_template_id !== $publishedTemplateId
            || (int) $snapshot->revision !== $publishedRevision
            || (int) $snapshot->revision !== $latestRevision
        ) {
            throw new GoneHttpException('Revisi rapor ini bukan revisi aktif yang dipublikasikan.');
        }
    }

    private function limitedUserAgent(?string $userAgent): ?string
    {
        $userAgent = trim((string) $userAgent);

        return $userAgent !== '' ? Str::limit($userAgent, 500, '') : null;
    }
}
