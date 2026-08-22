<?php

namespace App\Support\Assessment\Reporting;

use App\Enums\Assessment\ReportRunStatus;
use App\Models\Assessment\ClassReportArtifact;
use App\Models\Assessment\ReportGenerationRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

final class AssessmentReportCacheCleaner
{
    /**
     * @return array{files:int,bytes:int,artifacts:int}
     */
    public function clean(bool $apply = false): array
    {
        $disk = app(AssessmentReportStorage::class)->disk();
        $result = ['files' => 0, 'bytes' => 0, 'artifacts' => 0];

        if (! Schema::hasTable('assessment_class_report_artifacts')
            || ! Schema::hasColumn('assessment_class_report_artifacts', 'cache_expires_at')) {
            return $result;
        }

        $affectedRunIds = [];

        ClassReportArtifact::query()
            ->where('generation_status', 'completed')
            ->whereNotNull('cache_expires_at')
            ->where('cache_expires_at', '<=', Carbon::now())
            ->orderBy('id')
            ->chunkById(50, function ($artifacts) use ($apply, $disk, &$affectedRunIds, &$result): void {
                foreach ($artifacts as $artifact) {
                    $affectedRunIds[(int) $artifact->generation_run_id] = true;
                    $path = trim((string) $artifact->pdf_path);

                    if ($path !== '' && str_starts_with($path, 'assessment-reports/')) {
                        if ($disk->exists($path)) {
                            $result['files']++;
                            $result['bytes'] += (int) $disk->size($path);

                            if ($apply) {
                                $disk->delete($path);
                            }
                        }
                    }

                    $result['artifacts']++;

                    if ($apply) {
                        $artifact->forceFill([
                            'generation_status' => 'expired',
                            'pdf_path' => null,
                            'checksum' => null,
                            'error_message' => null,
                        ])->save();
                    }
                }
            });

        if ($apply && $affectedRunIds !== []) {
            ReportGenerationRun::query()
                ->whereKey(array_keys($affectedRunIds))
                ->each(function (ReportGenerationRun $run): void {
                    $completedClasses = $run->classArtifacts()
                        ->where('generation_status', 'completed')
                        ->whereNotNull('cache_expires_at')
                        ->where('cache_expires_at', '>', Carbon::now())
                        ->count();

                    $updates = ['completed_classes' => $completedClasses];

                    if ($run->status === ReportRunStatus::COMPLETED) {
                        $updates['status'] = ReportRunStatus::PREPARED;
                        $updates['completed_at'] = null;
                    }

                    $run->forceFill($updates)->save();
                });
        }

        return $result;
    }
}
