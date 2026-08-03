<?php

namespace App\Console\Commands;

use App\Support\Storage\AppStorageMaintenance;
use App\Support\Storage\HostingStorageAudit;
use Illuminate\Console\Command;

final class MaintainApplicationStorage extends Command
{
    protected $signature = 'app:storage-maintain
        {--dry-run : Hanya tampilkan kandidat}
        {--apply : Terapkan pembersihan aman}
        {--include-media-backups : Hapus backup media berumur 7 hari yang sudah diverifikasi manual}';

    protected $description = 'Bersihkan cache dan file sementara aplikasi melalui allowlist';

    public function handle(AppStorageMaintenance $maintenance, HostingStorageAudit $audit): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Pilih salah satu: --dry-run atau --apply.');

            return self::INVALID;
        }

        $apply = (bool) $this->option('apply');
        if ($this->option('include-media-backups') && ! $apply) {
            $this->error('--include-media-backups hanya boleh dipakai bersama --apply setelah verifikasi manual.');

            return self::INVALID;
        }

        $includeMediaBackups = $apply && (bool) $this->option('include-media-backups');
        $result = $maintenance->run($apply, $includeMediaBackups);
        $this->table(['Kategori', 'File', 'Ukuran', 'Mode'], collect($result)->map(
            fn (array $row, string $label): array => [
                $label,
                $row['files'],
                number_format($row['bytes'] / 1048576, 2, ',', '.').' MB',
                $apply && ($label !== 'Backup media terverifikasi' || $includeMediaBackups)
                    ? 'DIBERSIHKAN'
                    : 'DRY-RUN',
            ],
        )->values()->all());

        $audit->inspect();

        if (! $apply) {
            $this->line('Tidak ada file dihapus. Jalankan kembali dengan --apply setelah daftar diperiksa.');
        }

        return self::SUCCESS;
    }
}
