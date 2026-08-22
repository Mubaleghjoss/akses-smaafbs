<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TrafficAuditCommandTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = storage_path('framework/testing/traffic-audit.log');
        File::ensureDirectoryExists(dirname($this->logPath));
        File::put($this->logPath, implode(PHP_EOL, [
            '125.161.123.162 - - [10/Aug/2026:07:30:00 +0700] "GET /service-worker.js HTTP/2" 200 1298 "-" "Chrome"',
            '125.161.123.162 - - [10/Aug/2026:07:31:00 +0700] "GET /perpustakaan/program-literasi-numerasi/rukun HTTP/2" 503 807 "-" "Chrome"',
            '202.10.43.1 - - [10/Aug/2026:11:00:00 +0700] "GET / HTTP/2" 200 1200 "-" "Chrome"',
        ]).PHP_EOL);
    }

    protected function tearDown(): void
    {
        File::delete($this->logPath);

        parent::tearDown();
    }

    public function test_traffic_audit_reports_only_selected_time_range(): void
    {
        $this->artisan('app:traffic-audit', [
            '--date' => '2026-08-10',
            '--from' => '06:00',
            '--to' => '10:00',
            '--school-ip' => '125.161.123.162',
            '--log' => $this->logPath,
        ])
            ->expectsOutputToContain('Request diterima server: 2')
            ->expectsOutputToContain('IP publik unik: 1')
            ->expectsOutputToContain('Request IP sekolah: 2')
            ->expectsOutputToContain('Request yang gagal sebelum mencapai server tidak ada di access log')
            ->assertSuccessful();
    }
}
