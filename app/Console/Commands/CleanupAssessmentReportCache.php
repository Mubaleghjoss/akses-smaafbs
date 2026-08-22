<?php

namespace App\Console\Commands;

use App\Support\Assessment\Reporting\AssessmentReportCacheCleaner;
use Illuminate\Console\Command;

final class CleanupAssessmentReportCache extends Command
{
    protected $signature = 'assessment:cleanup-report-cache {--apply : Hapus cache PDF kelas yang kedaluwarsa}';

    protected $description = 'Inventaris atau hapus cache PDF kelas yang melewati masa 24 jam';

    public function handle(AssessmentReportCacheCleaner $cleaner): int
    {
        $apply = (bool) $this->option('apply');
        $result = $cleaner->clean($apply);
        $size = number_format($result['bytes'] / 1048576, 2, ',', '.').' MB';

        $this->table(['Cache kelas', 'File', 'Ukuran', 'Mode'], [[
            $result['artifacts'],
            $result['files'],
            $size,
            $apply ? 'DIHAPUS' : 'DRY-RUN',
        ]]);

        if (! $apply) {
            $this->line('Belum ada file dihapus. Tambahkan --apply setelah hasil diperiksa.');
        }

        return self::SUCCESS;
    }
}
