<?php

namespace Tests\Feature;

use App\Models\DataSiswa;
use App\Models\Rombel;
use App\Support\Security\EndpointProtectionPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TagihanStudentIntegrationTest extends TestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = str_repeat('a', 64);

        config([
            'tagihan_student_integration.token' => $this->token,
            'tagihan_student_integration.require_https' => false,
        ]);

        $this->createStudentTables();
    }

    public function test_endpoint_requires_a_valid_bearer_token_and_never_caches_errors(): void
    {
        $response = $this->getJson(route('api.integrations.tagihan.students'));

        $response
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken('invalid-token')
            ->getJson(route('api.integrations.tagihan.students'))
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);

        config(['tagihan_student_integration.token' => 'too-short']);

        $this->withToken('too-short')
            ->getJson(route('api.integrations.tagihan.students'))
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_endpoint_requires_https_when_enabled(): void
    {
        config(['tagihan_student_integration.require_https' => true]);

        $this->withToken($this->token)
            ->getJson(route('api.integrations.tagihan.students'))
            ->assertForbidden()
            ->assertExactJson(['message' => 'HTTPS is required.']);

        $this->withToken($this->token)
            ->getJson('https://localhost/api/v1/integrations/tagihan/students')
            ->assertOk();
    }

    public function test_endpoint_returns_only_the_whitelisted_paginated_student_contract(): void
    {
        Rombel::query()->create([
            'nama' => 'X.I / 2025-2026',
            'angkatan' => '2025-2026',
            'is_active' => true,
        ]);

        $student = DataSiswa::query()->create([
            'nama' => 'Putra Contoh',
            'billing_code' => 'AFBS-001',
            'nipd' => '25001',
            'nisn' => '0012345678',
            'rombel_saat_ini' => 'X.I / 2025-2026',
            'wa_ortu' => '081234567890',
            'status' => 'alumni',
            'updated_at' => '2026-07-15 08:30:00',
        ]);

        DataSiswa::query()->create([
            'nama' => 'Siswa Kedua',
            'billing_code' => 'AFBS-002',
            'status' => 'aktif',
        ]);

        $response = $this->withToken($this->token)
            ->getJson(route('api.integrations.tagihan.students', ['per_page' => 1]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.source_id', $student->id)
            ->assertJsonPath('data.0.billing_code', 'AFBS-001')
            ->assertJsonPath('data.0.nipd', '25001')
            ->assertJsonPath('data.0.nisn', '0012345678')
            ->assertJsonPath('data.0.nama', 'Putra Contoh')
            ->assertJsonPath('data.0.kelas', 'X.I / 2025-2026')
            ->assertJsonPath('data.0.angkatan', '2025-2026')
            ->assertJsonPath('data.0.wa_ortu', '081234567890')
            ->assertJsonPath('data.0.status', 'alumni')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total'],
            ]);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $payload = $response->json('data.0');
        $this->assertSame([
            'source_id',
            'billing_code',
            'nipd',
            'nisn',
            'nama',
            'kelas',
            'angkatan',
            'wa_ortu',
            'status',
            'source_updated_at',
            'checksum',
        ], array_keys($payload));

        $checksum = array_pop($payload);
        $this->assertSame(
            hash('sha256', json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            )),
            $checksum,
        );

        $this->withToken($this->token)
            ->getJson(route('api.integrations.tagihan.students', ['per_page' => 101]))
            ->assertUnprocessable();
    }

    public function test_endpoint_is_rate_limited_per_ip(): void
    {
        config([
            'endpoint_protection.named_limiters.tagihan_student_api.attempts' => 2,
            'endpoint_protection.named_limiters.tagihan_student_api.decay_seconds' => 60,
        ]);
        EndpointProtectionPolicy::registerNamedLimiters();

        $this->withToken($this->token)
            ->getJson(route('api.integrations.tagihan.students'))
            ->assertOk();

        $this->withToken($this->token)
            ->getJson(route('api.integrations.tagihan.students'))
            ->assertOk();

        $this->withToken($this->token)
            ->getJson(route('api.integrations.tagihan.students'))
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Terlalu banyak permintaan. Silakan coba lagi nanti.');
    }

    public function test_new_students_receive_unique_eight_character_codes_without_overwriting_existing_codes(): void
    {
        $first = DataSiswa::query()->create(['nama' => 'Siswa Baru Satu']);
        $second = DataSiswa::query()->create(['nama' => 'Siswa Baru Dua']);
        $existing = DataSiswa::query()->create([
            'nama' => 'Siswa Kode Lama',
            'billing_code' => 'KODE-LAMA-2025',
        ]);

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $first->billing_code);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $second->billing_code);
        $this->assertNotSame($first->billing_code, $second->billing_code);
        $this->assertSame('KODE-LAMA-2025', $existing->billing_code);
    }

    public function test_backfill_command_is_idempotent_and_preserves_existing_codes(): void
    {
        DB::table('data_siswa')->insert([
            [
                'nama' => 'Kode Null',
                'billing_code' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Kode Spasi',
                'billing_code' => '   ',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Kode Existing',
                'billing_code' => 'EXISTING-CODE',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->artisan('data-siswa:backfill-billing-codes', ['--dry-run' => true])
            ->expectsOutput('2 siswa membutuhkan billing code.')
            ->assertSuccessful();
        $this->assertSame(2, DB::table('data_siswa')->whereRaw("TRIM(COALESCE(billing_code, '')) = ''")->count());

        $this->artisan('data-siswa:backfill-billing-codes')
            ->expectsOutput('2 billing code siswa berhasil diisi.')
            ->assertSuccessful();

        $codes = DB::table('data_siswa')->orderBy('id')->pluck('billing_code')->all();
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $codes[0]);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $codes[1]);
        $this->assertNotSame($codes[0], $codes[1]);
        $this->assertSame('EXISTING-CODE', $codes[2]);

        $this->artisan('data-siswa:backfill-billing-codes')
            ->expectsOutput('0 billing code siswa berhasil diisi.')
            ->assertSuccessful();
        $this->assertSame($codes, DB::table('data_siswa')->orderBy('id')->pluck('billing_code')->all());
    }

    private function createStudentTables(): void
    {
        if (! Schema::hasTable('data_siswa')) {
            Schema::create('data_siswa', function (Blueprint $table): void {
                $table->id();
                $table->string('nama', 100);
                $table->string('kepribadian')->nullable();
                $table->string('gaya_belajar')->nullable();
                $table->string('profiling')->nullable();
                $table->string('mbti')->nullable();
                $table->string('rombel_saat_ini')->nullable();
                $table->string('billing_code', 32)->nullable()->unique();
                $table->string('wa_ortu', 32)->nullable();
                $table->string('nipd', 20)->nullable()->unique();
                $table->string('nisn', 20)->nullable()->unique();
                $table->string('status')->nullable()->default('aktif');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rombels')) {
            Schema::create('rombels', function (Blueprint $table): void {
                $table->id();
                $table->string('nama', 50)->unique();
                $table->string('angkatan', 20)->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('catatan')->nullable();
                $table->timestamps();
            });
        }
    }
}
