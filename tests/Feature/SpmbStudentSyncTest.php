<?php

namespace Tests\Feature;

use App\Models\DataSiswa;
use App\Models\SpmbSyncRun;
use App\Support\SpmbSync\SpmbApiClient;
use App\Support\SpmbSync\SpmbStudentSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SpmbStudentSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        config([
            'services.spmb_sync.base_url' => 'https://seleksi.example.test',
            'services.spmb_sync.token' => 'integration-token',
            'services.spmb_sync.timeout' => 5,
        ]);
    }

    public function test_api_client_fetches_all_paginated_rows_with_bearer_token(): void
    {
        Http::fake(function ($request) {
            $this->assertSame('Bearer integration-token', $request->header('Authorization')[0] ?? null);

            $page = (int) $request['page'];

            return Http::response([
                'data' => [[
                    'source_id' => (string) $page,
                    'nomor_pendaftaran' => "SPMB-{$page}",
                ]],
                'meta' => [
                    'last_page' => 2,
                    'total' => 2,
                ],
                'api_version' => '1.0',
            ]);
        });

        $rows = app(SpmbApiClient::class)->fetchAll();

        $this->assertCount(2, $rows);
        $this->assertSame(['1', '2'], collect($rows)->pluck('source_id')->all());
    }

    public function test_preview_matches_by_nisn_and_marks_ambiguous_students_as_conflict(): void
    {
        DataSiswa::query()->create([
            'nama' => 'Siswa Lama',
            'nisn' => '10001',
            'tanggal_lahir' => '2010-01-01',
            'status' => 'aktif',
        ]);
        DataSiswa::query()->create([
            'nama' => 'Nama Sama',
            'tanggal_lahir' => '2010-02-02',
            'status' => 'aktif',
        ]);
        DataSiswa::query()->create([
            'nama' => 'Nama Sama',
            'tanggal_lahir' => '2010-02-02',
            'status' => 'aktif',
        ]);

        $preview = app(SpmbStudentSyncService::class)->preview([
            $this->source('1', 'SPMB-001', 'Siswa Baru Nama', '10001'),
            $this->source('2', 'SPMB-002', 'Nama Sama', null, '2010-02-02'),
        ]);

        $this->assertSame('update', $preview['rows'][0]['status']);
        $this->assertSame('konflik', $preview['rows'][1]['status']);
        $this->assertCount(2, $preview['rows'][1]['candidates']);
    }

    public function test_apply_creates_and_updates_without_overwriting_local_operational_fields(): void
    {
        $existing = DataSiswa::query()->create([
            'nama' => 'Nama Lama',
            'nisn' => '10001',
            'nipd' => 'NIPD-LOCAL',
            'billing_code' => 'BILL-LOCAL',
            'rombel_saat_ini' => 'X.A / 2026-2027',
            'status' => 'aktif',
            'alamat' => 'Alamat Lama',
        ]);

        $sources = [
            $this->source('1', 'SPMB-001', 'Nama Baru', '10001', address: 'Alamat SPMB'),
            $this->source('2', 'SPMB-002', 'Siswa Baru', '10002'),
        ];

        $result = app(SpmbStudentSyncService::class)->apply(
            $sources,
            ['1', '2'],
            [],
            null,
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['updated']);

        $existing->refresh();
        $this->assertSame('Nama Baru', $existing->nama);
        $this->assertSame('Alamat SPMB', $existing->alamat);
        $this->assertSame('NIPD-LOCAL', $existing->nipd);
        $this->assertSame('BILL-LOCAL', $existing->billing_code);
        $this->assertSame('X.A / 2026-2027', $existing->rombel_saat_ini);
        $this->assertSame('aktif', $existing->status);
        $this->assertSame('SPMB-001', $existing->spmb_nomor_pendaftaran);

        $this->assertDatabaseHas('data_siswa', [
            'nama' => 'Siswa Baru',
            'spmb_nomor_pendaftaran' => 'SPMB-002',
            'status' => 'aktif',
            'rombel_saat_ini' => null,
        ]);

        $secondPreview = app(SpmbStudentSyncService::class)->preview($sources);
        $this->assertSame(2, $secondPreview['stats']['unchanged']);
    }

    public function test_apply_rolls_back_the_batch_and_logs_failure(): void
    {
        $sources = [
            $this->source('1', 'SPMB-DUPLICATE', 'Siswa Satu', '20001'),
            $this->source('2', 'SPMB-DUPLICATE', 'Siswa Dua', '20002'),
        ];

        try {
            app(SpmbStudentSyncService::class)->apply($sources, ['1', '2'], [], null);
            $this->fail('Sinkronisasi seharusnya gagal karena nomor SPMB duplikat.');
        } catch (\Throwable) {
            $this->assertDatabaseCount('data_siswa', 0);
            $this->assertSame('gagal', SpmbSyncRun::query()->latest('id')->value('status'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function source(
        string $sourceId,
        string $registration,
        string $name,
        ?string $nisn,
        string $birthDate = '2010-01-01',
        ?string $address = null,
    ): array {
        $payload = [
            'source_id' => $sourceId,
            'nomor_pendaftaran' => $registration,
            'source_updated_at' => '2026-07-02T12:00:00+07:00',
            'checksum' => hash('sha256', $registration),
            'biodata' => [
                'nama' => $name,
                'nisn' => $nisn,
                'jenis_kelamin' => 'L',
                'tanggal_lahir' => $birthDate,
                'alamat' => $address,
            ],
            'orang_tua' => [
                'telepon_ayah' => '081234567890',
            ],
            'sekolah_asal' => [
                'nama' => 'SMP Asal',
            ],
            'fisik' => [],
            'hasil_tes' => [
                'kepribadian' => 'Plegmatis',
                'gaya_belajar' => 'Visual',
                'profiling' => 'Emotional Quotient (EQ)',
                'mbti' => 'INFP',
            ],
        ];

        return $payload;
    }

    private function createTables(): void
    {
        Schema::create('rombels', function (Blueprint $table): void {
            $table->id();
            $table->string('nama')->unique();
            $table->string('angkatan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('data_siswa', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->string('jk', 2)->nullable();
            $table->string('nisn')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->text('alamat')->nullable();
            $table->string('sekolah_asal')->nullable();
            $table->string('wa_ortu')->nullable();
            $table->string('kepribadian')->nullable();
            $table->string('gaya_belajar')->nullable();
            $table->string('profiling')->nullable();
            $table->string('mbti')->nullable();
            $table->string('nipd')->nullable();
            $table->string('billing_code')->nullable();
            $table->string('rombel_saat_ini')->nullable();
            $table->string('status')->nullable();
            $table->string('spmb_nomor_pendaftaran')->nullable()->unique();
            $table->timestamp('spmb_source_updated_at')->nullable();
            $table->timestamp('spmb_synced_at')->nullable();
            $table->string('spmb_checksum', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('spmb_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status');
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('unchanged_count')->default(0);
            $table->unsignedInteger('conflict_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }
}
