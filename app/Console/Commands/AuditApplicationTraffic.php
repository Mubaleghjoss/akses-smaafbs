<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use RuntimeException;

class AuditApplicationTraffic extends Command
{
    protected $signature = 'app:traffic-audit
        {--date= : Tanggal WIB dalam format YYYY-MM-DD}
        {--from=00:00 : Jam awal HH:MM}
        {--to=23:59 : Jam akhir HH:MM}
        {--school-ip= : IP publik sekolah pada waktu kejadian}
        {--log= : Path access log atau gzip cPanel}';

    protected $description = 'Ringkas access log aplikasi tanpa menyimpan IP atau mengubah data';

    public function handle(): int
    {
        try {
            [$start, $end] = $this->range();
            $path = $this->resolveLogPath();
            $summary = $this->parse($path, $start, $end, trim((string) $this->option('school-ip')));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Audit trafik '.$start->format('d/m/Y H:i').'–'.$end->format('H:i').' WIB');
        $this->line('Sumber: '.$path);
        $this->line('Request diterima server: '.number_format($summary['requests'], 0, ',', '.'));
        $this->line('IP publik unik: '.number_format(count($summary['ips']), 0, ',', '.').' (bukan jumlah pengguna karena NAT)');

        if ($this->option('school-ip')) {
            $this->line('Request IP sekolah: '.number_format($summary['school_requests'], 0, ',', '.'));
        }

        $this->newLine();
        $this->table(['Status', 'Jumlah'], $this->rows($summary['statuses']));
        $this->table(['Jam', 'Request'], $this->rows($summary['hours']));
        $this->table(['Path', 'Request'], $this->rows(array_slice($summary['paths'], 0, 20, true)));
        $this->warn('Request yang gagal sebelum mencapai server tidak ada di access log. Gunakan telemetri browser dan monitor sekolah untuk melengkapinya.');

        return self::SUCCESS;
    }

    private function range(): array
    {
        $date = trim((string) ($this->option('date') ?: now()->format('Y-m-d')));
        $from = trim((string) $this->option('from'));
        $to = trim((string) $this->option('to'));

        foreach ([$from, $to] as $time) {
            if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
                throw new RuntimeException('Jam harus memakai format HH:MM.');
            }
        }

        try {
            $start = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$from, 'Asia/Jakarta')->startOfMinute();
            $end = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$to, 'Asia/Jakarta')->endOfMinute();
        } catch (\Throwable) {
            throw new RuntimeException('Tanggal harus memakai format YYYY-MM-DD.');
        }

        if ($end->lessThan($start)) {
            throw new RuntimeException('Jam akhir tidak boleh lebih kecil daripada jam awal.');
        }

        return [$start, $end];
    }

    private function resolveLogPath(): string
    {
        $explicit = trim((string) $this->option('log'));
        $configured = trim((string) config('literacy.traffic_audit.log_path'));

        foreach (array_filter([$explicit, $configured]) as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        $home = rtrim((string) getenv('HOME'), DIRECTORY_SEPARATOR);
        $matches = glob($home.'/logs/app.smaafbs.sch.id-ssl_log-*.gz') ?: [];
        usort($matches, fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));

        if ($matches !== []) {
            return $matches[0];
        }

        throw new RuntimeException('Access log tidak ditemukan. Gunakan opsi --log=/path/file.log.gz.');
    }

    private function parse(string $path, Carbon $start, Carbon $end, string $schoolIp): array
    {
        $summary = [
            'requests' => 0,
            'school_requests' => 0,
            'ips' => [],
            'statuses' => [],
            'hours' => [],
            'paths' => [],
        ];
        $reader = str_ends_with(strtolower($path), '.gz')
            ? gzopen($path, 'rb')
            : fopen($path, 'rb');

        if ($reader === false) {
            throw new RuntimeException('Access log tidak dapat dibaca.');
        }

        try {
            while (($line = str_ends_with(strtolower($path), '.gz') ? gzgets($reader) : fgets($reader)) !== false) {
                if (preg_match('/^(\S+) \S+ \S+ \[([^]]+)] "\S+ ([^ ?"]+)[^"]*" (\d{3})/', $line, $matches) !== 1) {
                    continue;
                }

                $timestamp = Carbon::createFromFormat('d/M/Y:H:i:s O', $matches[2], 'Asia/Jakarta');

                if ($timestamp->lessThan($start) || $timestamp->greaterThan($end)) {
                    continue;
                }

                [$ip, $pathValue, $status] = [$matches[1], $matches[3], $matches[4]];
                $summary['requests']++;
                $summary['ips'][$ip] = true;
                $summary['school_requests'] += $schoolIp !== '' && hash_equals($schoolIp, $ip) ? 1 : 0;
                $summary['statuses'][$status] = ($summary['statuses'][$status] ?? 0) + 1;
                $hour = $timestamp->format('H:00');
                $summary['hours'][$hour] = ($summary['hours'][$hour] ?? 0) + 1;
                $summary['paths'][$pathValue] = ($summary['paths'][$pathValue] ?? 0) + 1;
            }
        } finally {
            str_ends_with(strtolower($path), '.gz') ? gzclose($reader) : fclose($reader);
        }

        arsort($summary['statuses']);
        ksort($summary['hours']);
        arsort($summary['paths']);

        return $summary;
    }

    private function rows(array $values): array
    {
        return collect($values)
            ->map(fn (int $count, string|int $label): array => [(string) $label, number_format($count, 0, ',', '.')])
            ->values()
            ->all();
    }
}
