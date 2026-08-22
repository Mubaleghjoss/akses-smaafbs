<?php

namespace App\Console\Commands;

use App\Support\Storage\HostingStorageAudit;
use Illuminate\Console\Command;

final class AuditApplicationStorage extends Command
{
    protected $signature = 'app:storage-audit
        {--json : Tampilkan JSON}
        {--used-bytes= : Total penggunaan hosting dari hasil du cPanel}';

    protected $description = 'Audit penggunaan storage aplikasi atau home hosting tanpa menghapus file';

    public function handle(HostingStorageAudit $audit): int
    {
        $usedBytes = $this->option('used-bytes');
        if ($usedBytes !== null && (! ctype_digit((string) $usedBytes) || (int) $usedBytes < 0)) {
            $this->error('--used-bytes harus berupa bilangan byte non-negatif.');

            return self::INVALID;
        }
        $result = $audit->inspect(true, $usedBytes !== null ? (int) $usedBytes : null);

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Penggunaan: '.$this->size($result['used_bytes']).' / '.$this->size($result['quota_bytes']).' ('.($result['percent'] ?? '-').'%)');
        $this->table(['Kategori', 'Ukuran'], collect($result['categories'])->map(
            fn (int $bytes, string $label): array => [$label, $this->size($bytes)],
        )->values()->all());

        return self::SUCCESS;
    }

    private function size(int $bytes): string
    {
        return number_format($bytes / 1048576, 2, ',', '.').' MB';
    }
}
