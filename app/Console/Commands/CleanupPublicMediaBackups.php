<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupPublicMediaBackups extends Command
{
    protected $signature = 'media:cleanup-public-backups
        {--days=7 : Umur minimum backup yang boleh dibersihkan}
        {--apply : Hapus backup yang memenuhi umur minimum}';

    protected $description = 'Inventaris atau bersihkan manifest backup media yang sudah diverifikasi';

    public function handle(): int
    {
        $days = max(7, (int) $this->option('days'));
        $cutoff = now()->subDays($days)->getTimestamp();
        $disk = Storage::disk('local');
        $directories = collect($disk->directories('media-backups'))
            ->filter(function (string $directory) use ($disk, $cutoff): bool {
                $manifest = $directory.'/manifest.json';

                return $disk->exists($manifest) && $disk->lastModified($manifest) <= $cutoff;
            })
            ->values();

        if ($directories->isEmpty()) {
            $this->info('Tidak ada backup media berumur '.$days.' hari atau lebih.');

            return self::SUCCESS;
        }

        $this->line('Backup yang memenuhi syarat:');
        $directories->each(fn (string $directory) => $this->line('- '.basename($directory)));

        if (! $this->option('apply')) {
            $this->newLine();
            $this->line('Belum ada file dihapus. Tambahkan --apply setelah hasil produksi diverifikasi.');

            return self::SUCCESS;
        }

        $directories->each(fn (string $directory) => $disk->deleteDirectory($directory));
        $this->info($directories->count().' backup media telah dibersihkan.');

        return self::SUCCESS;
    }
}
