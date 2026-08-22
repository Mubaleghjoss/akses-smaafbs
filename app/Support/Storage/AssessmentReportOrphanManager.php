<?php

namespace App\Support\Storage;

use App\Support\Assessment\Reporting\AssessmentReportStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class AssessmentReportOrphanManager
{
    /** @return array{files:int,bytes:int,paths:array<int,string>} */
    public function inspect(): array
    {
        $disk = app(AssessmentReportStorage::class)->disk();
        $referenced = $this->referencedPaths();
        $paths = [];
        $bytes = 0;

        foreach ($disk->allFiles('assessment-reports') as $path) {
            if (str_starts_with($path, 'assessment-reports/.tmp/') || isset($referenced[$path])) {
                continue;
            }

            $paths[] = $path;
            $bytes += (int) $disk->size($path);
        }

        return ['files' => count($paths), 'bytes' => $bytes, 'paths' => $paths];
    }

    /** @return array{files:int,bytes:int} */
    public function quarantine(bool $apply): array
    {
        $disk = app(AssessmentReportStorage::class)->disk();
        $orphans = $this->inspect();

        if ($apply) {
            $batch = now()->format('Ymd-His');

            foreach ($orphans['paths'] as $path) {
                $relative = Str::after($path, 'assessment-reports/');
                $target = "orphan-quarantine/assessment-reports/{$batch}/{$relative}";
                $disk->makeDirectory(dirname($target));
                $disk->move($path, $target);
            }
        }

        return ['files' => $orphans['files'], 'bytes' => $orphans['bytes']];
    }

    /** @return array{files:int,bytes:int} */
    public function purgeExpired(bool $apply, int $days = 7): array
    {
        $disk = app(AssessmentReportStorage::class)->disk();
        $cutoff = now()->subDays(max(7, $days))->getTimestamp();
        $files = 0;
        $bytes = 0;

        foreach ($disk->allFiles('orphan-quarantine/assessment-reports') as $path) {
            if ($disk->lastModified($path) > $cutoff) {
                continue;
            }

            $files++;
            $bytes += (int) $disk->size($path);

            if ($apply) {
                $disk->delete($path);
            }
        }

        return compact('files', 'bytes');
    }

    /** @return array<string,true> */
    private function referencedPaths(): array
    {
        $paths = [];

        if (Schema::hasTable('assessment_report_snapshots')) {
            DB::table('assessment_report_snapshots')
                ->whereNotNull('pdf_path')
                ->where('pdf_path', '!=', '')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$paths): void {
                    foreach ($rows as $row) {
                        $paths[(string) $row->pdf_path] = true;
                    }
                });
        }

        if (Schema::hasTable('assessment_class_report_artifacts')) {
            DB::table('assessment_class_report_artifacts')
                ->whereNotNull('pdf_path')
                ->where('pdf_path', '!=', '')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (&$paths): void {
                    foreach ($rows as $row) {
                        $paths[(string) $row->pdf_path] = true;
                    }
                });
        }

        return $paths;
    }
}
