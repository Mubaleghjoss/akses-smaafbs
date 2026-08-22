<?php

namespace App\Support\Assessment\Reporting;

use App\Models\Assessment\ClassReportArtifact;
use App\Models\Assessment\ReportSnapshot;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AssessmentReportStorage
{
    public const DISK = 'local';

    public function disk(): Filesystem
    {
        $disk = (string) config('assessment.reports.disk', self::DISK);

        if ($disk !== self::DISK) {
            throw new RuntimeException(
                'Disk rapor Penilaian wajib "local" agar file tetap berada di storage privat.',
            );
        }

        $configuredRoot = str_replace('\\', '/', (string) config('filesystems.disks.local.root'));
        $publicRoot = rtrim(str_replace('\\', '/', public_path()), '/').'/';

        if ($configuredRoot === '' || str_starts_with(rtrim($configuredRoot, '/').'/', $publicRoot)) {
            throw new RuntimeException(
                'Root disk rapor Penilaian tidak boleh berada di dalam webroot publik.',
            );
        }

        return Storage::disk(self::DISK);
    }

    public function individualPath(ReportSnapshot $snapshot): string
    {
        $payload = $snapshot->snapshot_data ?? [];
        $period = $this->safeSegment(data_get($payload, 'period.code', 'periode'));
        $type = $this->safeSegment(data_get($payload, 'period.type', 'rapor'));
        $class = $this->safeSegment(data_get($payload, 'student.class_name', 'kelas'));
        $identifier = $this->safeSegment(
            data_get($payload, 'student.nis')
                ?: data_get($payload, 'student.nisn')
                ?: 'siswa-'.$snapshot->assessment_period_student_id
        );

        $filename = implode('-', [
            strtoupper($type),
            strtoupper($period),
            strtoupper($class),
            $identifier,
            'R'.max(1, (int) $snapshot->revision),
        ]).'.pdf';

        return sprintf(
            $this->basePath().'/period-%d/template-%d/students/student-%d/%s',
            (int) $snapshot->assessment_period_id,
            (int) $snapshot->assessment_report_template_id,
            (int) $snapshot->assessment_period_student_id,
            $filename,
        );
    }

    public function classPath(ClassReportArtifact $artifact): string
    {
        $className = $this->safeSegment(
            $artifact->periodRombel?->rombel_name_snapshot
                ?: 'kelas-'.$artifact->assessment_period_rombel_id
        );

        return sprintf(
            $this->basePath().'/period-%d/template-%d/classes/class-%d/%s-R%d.pdf',
            (int) $artifact->assessment_period_id,
            (int) $artifact->assessment_report_template_id,
            (int) $artifact->assessment_period_rombel_id,
            strtoupper($className),
            max(1, (int) $artifact->revision),
        );
    }

    /**
     * Store the completed PDF through a temporary file on the same private disk.
     *
     * @return array{path:string,checksum:string}
     */
    public function putAtomically(string $path, string $contents): array
    {
        if ($contents === '' || ! str_starts_with($contents, '%PDF-')) {
            throw new RuntimeException('Generator tidak menghasilkan dokumen PDF yang valid.');
        }

        $temporaryPath = $this->basePath().'/.tmp/'.Str::uuid().'.pdf';
        $disk = $this->disk();

        if (! $disk->put($temporaryPath, $contents)) {
            throw new RuntimeException('PDF sementara gagal disimpan pada storage privat.');
        }

        try {
            $checksum = hash('sha256', $contents);
            $storedChecksum = hash_file('sha256', $disk->path($temporaryPath));

            if (! is_string($storedChecksum) || ! hash_equals($checksum, $storedChecksum)) {
                throw new RuntimeException('Checksum PDF sementara tidak sesuai.');
            }

            $disk->makeDirectory(dirname($path));
            $disk->delete($path);

            if (! $disk->move($temporaryPath, $path)) {
                throw new RuntimeException('PDF gagal dipindahkan ke lokasi akhir.');
            }

            return [
                'path' => $path,
                'checksum' => $checksum,
            ];
        } finally {
            $disk->delete($temporaryPath);
        }
    }

    public function isValid(?string $path, ?string $checksum): bool
    {
        $path = trim((string) $path);
        $checksum = strtolower(trim((string) $checksum));

        if ($path === '' || preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1) {
            return false;
        }

        $disk = $this->disk();

        if (! $disk->exists($path)) {
            return false;
        }

        $actual = hash_file('sha256', $disk->path($path));

        return is_string($actual) && hash_equals($checksum, strtolower($actual));
    }

    public function downloadName(ReportSnapshot|ClassReportArtifact $report): string
    {
        $path = trim((string) $report->pdf_path);

        return basename($path !== '' ? $path : 'rapor.pdf');
    }

    private function safeSegment(mixed $value): string
    {
        $segment = Str::slug((string) $value, '-');

        return $segment !== '' ? mb_substr($segment, 0, 80) : 'data';
    }

    private function basePath(): string
    {
        $path = trim((string) config('assessment.reports.path', 'assessment-reports'), '/\\');

        return $path !== '' ? $path : 'assessment-reports';
    }
}
