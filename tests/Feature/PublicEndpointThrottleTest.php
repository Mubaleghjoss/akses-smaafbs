<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\DataSiswa;
use App\Models\PerpustakaanBuku;
use App\Models\SppBill;
use App\Support\Security\EndpointProtectionPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicEndpointThrottleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapPublicTables();

        config([
            'endpoint_protection.named_limiters.public_billing_lookup.attempts' => 1,
            'endpoint_protection.named_limiters.public_billing_lookup.decay_seconds' => 60,
            'endpoint_protection.named_limiters.public_billing_payment_upload.attempts' => 1,
            'endpoint_protection.named_limiters.public_billing_payment_upload.decay_seconds' => 60,
            'endpoint_protection.named_limiters.public_library_downloads.attempts' => 1,
            'endpoint_protection.named_limiters.public_library_downloads.decay_seconds' => 60,
            'endpoint_protection.named_limiters.public_agenda_events.attempts' => 1,
            'endpoint_protection.named_limiters.public_agenda_events.decay_seconds' => 60,
        ]);

        EndpointProtectionPolicy::registerNamedLimiters();
    }

    public function test_normal_public_browsing_pages_remain_reachable(): void
    {
        $this->get('/')->assertOk();
        $this->get('/berita')->assertOk();
        $this->get('/agenda')->assertOk();
        $this->get('/perpustakaan')->assertOk();
    }

    public function test_billing_lookup_is_allowed_then_throttled_with_friendly_flash(): void
    {
        DataSiswa::query()->create([
            'nama' => 'Siswa Billing',
            'billing_code' => 'BILL-001',
            'rombel_saat_ini' => 'X-A',
        ]);

        $this->get(route('billing.show', ['code' => 'BILL-001']))->assertOk();

        $this->get(route('billing.show', ['code' => 'BILL-001']))
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('error', 'Permintaan cek tagihan terlalu sering. Silakan tunggu sebentar lalu coba lagi.');
    }

    public function test_billing_upload_submit_is_allowed_then_throttled_with_validation_friendly_feedback(): void
    {
        $student = DataSiswa::query()->create([
            'nama' => 'Siswa Upload',
            'billing_code' => 'BILL-002',
            'rombel_saat_ini' => 'X-B',
        ]);

        $bill = SppBill::query()->create([
            'siswa_id' => $student->id,
            'period_year' => 2026,
            'period_month' => 3,
            'amount' => 200000,
            'paid_amount' => 0,
            'payment_status' => 'none',
            'status' => 'unpaid',
        ]);

        $formUrl = route('billing.pay.form', ['code' => $student->billing_code, 'bill_id' => $bill->id]);

        $this->from($formUrl)
            ->post(route('billing.pay.submit'), [
                'code' => $student->billing_code,
                'bill_id' => $bill->id,
                'amount' => 50000,
            ])
            ->assertRedirect($formUrl)
            ->assertSessionHasErrors(['proof_camera', 'proof_file']);

        $this->from($formUrl)
            ->post(route('billing.pay.submit'), [
                'code' => $student->billing_code,
                'bill_id' => $bill->id,
                'amount' => 50000,
            ])
            ->assertRedirect($formUrl)
            ->assertSessionHasErrors(['throttle']);
    }

    public function test_library_download_is_allowed_then_throttled_with_friendly_flash(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('ebooks/sample.pdf', 'dummy-content');

        PerpustakaanBuku::query()->insert([
            'judul_buku' => 'Ebook Rate Limited',
            'penulis' => 'Tester',
            'file_type' => 'ebook',
            'status' => 'aktif',
            'file_path' => 'ebooks/sample.pdf',
            'download_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $book = PerpustakaanBuku::query()->latest('id')->firstOrFail();

        $showUrl = route('library.show', $book);

        $this->withHeader('referer', $showUrl)
            ->get(route('library.download', $book))
            ->assertOk();

        $this->withHeader('referer', $showUrl)
            ->get(route('library.download', $book))
            ->assertRedirect($showUrl)
            ->assertSessionHas('error', 'Unduhan terlalu sering dari perangkat ini. Silakan tunggu sebentar lalu coba lagi.');
    }

    public function test_agenda_events_returns_json_message_when_throttled(): void
    {
        CalendarEvent::query()->create([
            'title' => 'External Event',
            'visibility' => 'external',
            'all_day' => true,
            'start' => now()->startOfDay(),
            'end' => now()->endOfDay(),
        ]);

        $this->getJson(route('agenda.events'))->assertOk();

        $this->getJson(route('agenda.events'))
            ->assertStatus(429)
            ->assertJsonPath('message', 'Permintaan agenda terlalu sering. Silakan coba lagi dalam beberapa saat.');
    }

    private function bootstrapPublicTables(): void
    {
        if (! Schema::hasTable('data_siswa')) {
            Schema::create('data_siswa', function (Blueprint $table): void {
                $table->id();
                $table->string('nama')->nullable();
                $table->string('billing_code')->nullable();
                $table->string('rombel_saat_ini')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('berita')) {
            Schema::create('berita', function (Blueprint $table): void {
                $table->id();
                $table->string('judul')->nullable();
                $table->text('konten')->nullable();
                $table->string('status')->nullable();
                $table->date('tanggal_berita')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('calendar_events')) {
            Schema::create('calendar_events', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('visibility')->nullable();
                $table->boolean('all_day')->default(true);
                $table->dateTime('start')->nullable();
                $table->dateTime('end')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('perpustakaan_buku')) {
            Schema::create('perpustakaan_buku', function (Blueprint $table): void {
                $table->id();
                $table->string('judul_buku')->nullable();
                $table->string('penulis')->nullable();
                $table->string('penerbit')->nullable();
                $table->text('deskripsi')->nullable();
                $table->string('file_type')->nullable();
                $table->string('status')->nullable();
                $table->string('file_path')->nullable();
                $table->unsignedInteger('download_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('spp_fee_types')) {
            Schema::create('spp_fee_types', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('spp_bills')) {
            Schema::create('spp_bills', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('siswa_id');
                $table->unsignedBigInteger('fee_type_id')->nullable();
                $table->unsignedSmallInteger('period_month')->nullable();
                $table->unsignedSmallInteger('period_year')->nullable();
                $table->unsignedInteger('amount')->default(0);
                $table->unsignedInteger('paid_amount')->default(0);
                $table->string('payment_status')->default('none');
                $table->string('status')->default('unpaid');
                $table->date('due_date')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('spp_payment_attachments')) {
            Schema::create('spp_payment_attachments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('bill_id');
                $table->unsignedInteger('amount')->default(0);
                $table->string('status')->nullable();
                $table->string('file_name')->nullable();
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->dateTime('uploaded_at')->nullable();
                $table->text('verification_notes')->nullable();
            });
        }
    }
}
