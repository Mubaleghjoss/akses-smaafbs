<?php

namespace Tests\Feature;

use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource\Pages\CreatePerpustakaanLiterasiMaterial;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource\Pages\ListPerpustakaanLiterasiMaterials;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource\Pages\StudentHistoryPerpustakaanLiterasi;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource\Pages\ViewPerpustakaanLiterasiMaterial;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource\RelationManagers\ResponsesRelationManager;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource\RelationManagers\SimilarityMatchesRelationManager;
use App\Filament\Widgets\PerpustakaanLiterasiGlobalAnalytics;
use App\Jobs\AnalyzeLiteracyResponseSimilarity;
use App\Jobs\QueueLiteracySimilarityReanalysis;
use App\Models\DataSiswa;
use App\Models\Pengaturan;
use App\Models\PerpustakaanLiterasiAnswer;
use App\Models\PerpustakaanLiterasiDispensation;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiNetworkCheck;
use App\Models\PerpustakaanLiterasiQuestion;
use App\Models\PerpustakaanLiterasiResponse;
use App\Models\PerpustakaanLiterasiSimilarityMatch;
use App\Models\PerpustakaanLiterasiSubmissionEvent;
use App\Models\PerpustakaanLiterasiSubmissionQueueState;
use App\Models\PerpustakaanLiterasiSubmissionTicket;
use App\Models\User;
use App\Support\Admin\AdminModuleAccess;
use App\Support\Perpustakaan\LiteracyCompletionShareText;
use App\Support\Perpustakaan\LiteracyMonthlyShareText;
use App\Support\Perpustakaan\LiteracyOperationalHealth;
use App\Support\Perpustakaan\LiteracyReceiptClassStatus;
use App\Support\Perpustakaan\LiteracySubmissionQueue;
use App\Support\Perpustakaan\LiterasiAnalytics;
use App\Support\Perpustakaan\LiterasiSimilarityAnalyzer;
use App\Support\SiteSettings\SiteSettingKeys;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Concerns\BootstrapsAdminFeatureTables;
use Tests\TestCase;

class LibraryLiteracyProgramTest extends TestCase
{
    use BootstrapsAdminFeatureTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->bootstrapAdminFeatureTables();
        $this->runLiteracyProgramMigration();
        $this->runLiteracyQuestionSettingsMigration();
        $this->runLiteracyGradingMigration();
        $this->runLiteracySimilarityReviewMigration();
        $this->runLiteracySoftDeletesMigration();
        $this->runLiterasiNumerasiProgramMigration();
        $this->runLiteracyInstructionsMigration();
        $this->runLiteracyStudentVerificationSettingMigration();
        $this->runLiteracySubmissionQueueMigration();
        $this->runLiteracySubmissionRequestKeyMigration();
        $this->runLiteracySubmissionDeliveryMigration();
        $this->runLiteracyOperationalMonitoringMigration();
        $this->runLiteracyObjectiveQuestionMigration();
        $this->runLiteracyDispensationMigration();
        $this->bootstrapPengaturanTable();
        $this->bootstrapLibraryHubTables();
    }

    public function test_public_index_only_shows_available_materials(): void
    {
        $active = $this->createMaterial('Materi Aktif Literasi', [
            'reading_content' => 'Preview rumus: \(x^{2}\) dan \(\frac{a}{b}\).',
            'closes_at' => now()->addDay()->setTime(15, 30),
        ]);
        $active->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Apa inti bacaan ini?',
            'max_characters' => 1000,
        ]);

        $this->createMaterial('Materi Nonaktif', ['is_active' => false]);
        $this->createMaterial('Materi Masa Depan', ['opens_at' => now()->addDay()->toDateString()]);

        $this->get(route('library.literacy.index'))
            ->assertOk()
            ->assertSee('Literasi Numerasi')
            ->assertSee('Materi Aktif Literasi')
            ->assertSee('Literacy Habituation Programme')
            ->assertSee('Tutup '.$active->closes_at->format('d/m/Y H:i'))
            ->assertDontSee('tex-chtml.js', false)
            ->assertSee('\(x^{2}\)', false)
            ->assertSee('\(\frac{a}{b}\)', false)
            ->assertDontSee('Materi Nonaktif')
            ->assertDontSee('Materi Masa Depan');
    }

    public function test_literasi_shortcut_redirects_to_literacy_program(): void
    {
        $this->get('/literasi')
            ->assertRedirect('/perpustakaan/program-literasi-numerasi');

        $this->get('/perpustakaan/literacy-habituation-program')
            ->assertRedirect('/perpustakaan/program-literasi-numerasi');
    }

    public function test_successful_submit_uses_private_receipt_without_questions_or_answers(): void
    {
        Queue::fake();

        $student = $this->createStudent('Codex Struk Aman', 'XI Struk');
        $missingPeer = $this->createStudent('Codex Teman Belum Mengisi', 'XI Struk');
        $dispensatedPeer = $this->createStudent('Codex Teman Sakit', 'XI Struk');
        $otherClassStudent = $this->createStudent('Codex Kelas Lain', 'XII Lain');
        $material = $this->createMaterial('Materi Struk Aman', [
            'student_verification_enabled' => false,
        ]);
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Pertanyaan rahasia yang tidak boleh tampil di struk.',
            'min_characters' => 10,
            'max_characters' => 500,
        ]);
        $answer = 'Jawaban rahasia murid yang tidak boleh tampil di struk.';
        PerpustakaanLiterasiDispensation::query()->create([
            'material_id' => $material->getKey(),
            'data_siswa_id' => $dispensatedPeer->getKey(),
            'reason' => PerpustakaanLiterasiDispensation::REASON_SICK,
            'student_name_snapshot' => $dispensatedPeer->nama,
            'student_class_snapshot' => $dispensatedPeer->rombel_saat_ini,
            'confirmed_at' => now(),
        ]);

        $response = $this->post(route('library.literacy.store', $material->slug), [
            'student_id' => $student->getKey(),
            'answers' => [
                $question->getKey() => $answer,
            ],
        ])->assertRedirect(route('library.literacy.completed'));

        $response->assertSessionHas('literacy_submission_receipt.student_name', $student->nama);
        $response->assertSessionHas('literacy_submission_receipt.student_id', $student->getKey());
        $response->assertSessionHas('literacy_submission_receipt.material_id', $material->getKey());

        $storedResponse = PerpustakaanLiterasiResponse::query()
            ->where('material_id', $material->getKey())
            ->where('data_siswa_id', $student->getKey())
            ->firstOrFail();

        $receipt = $this->get(route('library.literacy.completed'))
            ->assertOk()
            ->assertHeaderContains('cache-control', 'no-store')
            ->assertSee('Jawaban berhasil disimpan')
            ->assertSee($student->nama)
            ->assertSee($storedResponse->edit_code)
            ->assertSee('Salin Kode Edit')
            ->assertSee('Isi Murid Berikutnya')
            ->assertSee('Amal Salih Hari Ini')
            ->assertSee('Ingatkan teman yang belum mengisi')
            ->assertSee('Sudah Mengisi')
            ->assertSee('Belum Mengisi')
            ->assertSee($missingPeer->nama)
            ->assertSee($dispensatedPeer->nama)
            ->assertSee('Dispensasi—Tidak Perlu Diingatkan')
            ->assertSee('Sakit')
            ->assertSee('Kamu')
            ->assertDontSee($otherClassStudent->nama)
            ->assertDontSee($question->prompt)
            ->assertDontSee($answer)
            ->assertDontSee(route('library.literacy.edit', $storedResponse->shortEditCode()), false);

        $this->get(route('library.literacy.completed'))
            ->assertOk()
            ->assertDontSee($storedResponse->edit_code)
            ->assertSee('Data struk sudah ditutup');

        $this->get(route('library.literacy.show', $material->slug))
            ->assertHeaderContains('cache-control', 'no-store')
            ->assertSee('window.location.replace(payload.redirect_url);', false)
            ->assertSee('unexpected_success_payload')
            ->assertSee('Periksa Status Lagi')
            ->assertSee('Kembali Perbaiki Jawaban')
            ->assertSee('Belum dapat dipastikan apakah jawaban sudah tersimpan')
            ->assertSee("'X-Literacy-Client': 'async-v2'", false)
            ->assertSee("redirect: 'manual'", false)
            ->assertSee('let replayUsed = false;', false)
            ->assertDontSee('Server tidak mengirim tujuan halaman hasil.')
            ->assertDontSee('Server sudah menerima proses pengiriman, tetapi Struk belum berhasil dibuka')
            ->assertSee("window.addEventListener('pageshow'", false)
            ->assertSee('if (!event.persisted)', false);
    }

    public function test_direct_link_accepts_inactive_and_closed_material_but_future_material_is_locked(): void
    {
        Queue::fake();

        $student = $this->createStudent('Codex Direct Link', 'XI Direct');
        $material = $this->createMaterial('Materi Direct Link Tertutup', [
            'is_active' => false,
            'opens_at' => now()->subDays(2),
            'closes_at' => now()->subDay(),
            'student_verification_enabled' => false,
        ]);
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jelaskan fungsi direct link.',
            'min_characters' => 10,
            'max_characters' => 500,
        ]);

        $this->get(route('library.literacy.index'))
            ->assertOk()
            ->assertDontSee($material->title);

        $this->get(route('library.literacy.show', $material->slug))
            ->assertOk()
            ->assertSee('Materi dibuka melalui direct link')
            ->assertSee($question->prompt);

        $this->post(route('library.literacy.store', $material->slug), [
            'student_id' => $student->getKey(),
            'answers' => [
                $question->getKey() => 'Direct link tetap menerima jawaban walaupun materi tidak ada di daftar.',
            ],
        ])->assertRedirect(route('library.literacy.completed'));

        $future = $this->createMaterial('Materi Masa Depan Terkunci', [
            'opens_at' => now()->addDay(),
            'student_verification_enabled' => false,
            'reading_content' => 'Isi bacaan rahasia sebelum waktu buka.',
        ]);
        $futureQuestion = $future->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Pertanyaan rahasia masa depan.',
            'max_characters' => 500,
        ]);

        $this->get(route('library.literacy.show', $future->slug))
            ->assertOk()
            ->assertSee('Materi Belum Dibuka')
            ->assertDontSee('Isi bacaan rahasia sebelum waktu buka.')
            ->assertDontSee($futureQuestion->prompt)
            ->assertDontSee('data-literacy-answer-form', false);

        $this->postJson(route('library.literacy.queue.store', $future->slug), [
            'student_id' => $student->getKey(),
            'submission_request_id' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonValidationErrors('material');

        $this->postJson(route('library.literacy.store', $future->slug), [
            'student_id' => $student->getKey(),
            'answers' => [
                $futureQuestion->getKey() => 'Tidak boleh masuk.',
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('material');

        $deleted = $this->createMaterial('Materi Direct Link Dihapus');
        $deleted->delete();

        $this->get(route('library.literacy.show', $deleted->slug))->assertNotFound();
    }

    public function test_public_literasi_numerasi_page_shows_category_video_tatib_and_header_perpus_link(): void
    {
        $this->createStudent('Codex Numerasi Siswa', 'X Numerasi');
        $material = $this->createMaterial('Materi Numerasi Video', [
            'program_category' => PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE,
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'instructions' => "Baca arahan khusus dari admin.\nJangan membuka tab lain selama mengerjakan.",
        ]);
        $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jelaskan pola angka pada video.',
            'min_characters' => 10,
            'max_characters' => 500,
        ]);

        $this->get('/perpustakaan')
            ->assertOk()
            ->assertSee('Akses Perpus')
            ->assertDontSee('Cari Buku Perpus')
            ->assertDontSee('Ringkasan Aktivitas Perpus')
            ->assertDontSee('Form Aktivitas Perpus')
            ->assertDontSee('Input Hasil Literasi Perpus');

        $this->get(route('library.literacy.index'))
            ->assertOk()
            ->assertSee('Literasi Numerasi')
            ->assertSee('Arahan dan tata tertib pengerjaan')
            ->assertSee('Akses Perpus')
            ->assertSee('Numeracy Excellence Programme');

        $this->get('/perpustakaan/literacy-habituation-program/'.$material->slug)
            ->assertRedirect(route('library.literacy.show', $material->slug));

        $this->get(route('library.literacy.show', $material->slug))
            ->assertOk()
            ->assertSee('Numeracy Excellence Programme')
            ->assertSee('Arahan dan tata tertib pengerjaan')
            ->assertSee('Akses Perpus')
            ->assertSee('Baca arahan khusus dari admin.')
            ->assertSee('Jangan membuka tab lain selama mengerjakan.')
            ->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('data-literacy-integrity-form', false)
            ->assertSee('data-integrity-field="app_hidden_count"', false)
            ->assertDontSee('data-integrity-field="tab_switch_count"', false)
            ->assertDontSee('data-integrity-field="page_leave_attempt_count"', false)
            ->assertDontSee("window.addEventListener('blur'", false)
            ->assertSee('}, 10000);', false)
            ->assertDontSee('Peringatan Integritas')
            ->assertDontSee('Tetap keluar dari halaman pengerjaan?');
    }

    public function test_public_literacy_images_normalize_legacy_filename_only_paths(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('literasi/materials/legacy-material.png', $this->makeTestPng(900, 600));

        $this->createStudent('Codex Literasi Siswa Gambar', 'X IPA');
        $material = $this->createMaterial('Materi Gambar Legacy', [
            'image_path' => 'legacy-material.png',
        ]);
        $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jelaskan gambar pada pertanyaan.',
            'image_path' => 'legacy-question.png',
            'max_characters' => 500,
        ]);

        $this->get(route('library.literacy.index'))
            ->assertOk()
            ->assertSee('/storage/literasi/materials/legacy-material.png', false);

        $thumbnailUrl = route('library.literacy.social-thumbnail', [
            'slug' => $material->slug,
            'v' => $material->updated_at?->timestamp,
        ]);

        $this->get(route('library.literacy.show', $material->slug))
            ->assertOk()
            ->assertSee('/storage/literasi/materials/legacy-material.png', false)
            ->assertSee('<meta property="og:title" content="Materi Gambar Legacy">', false)
            ->assertSee('<meta property="og:image" content="'.$thumbnailUrl.'">', false)
            ->assertSee('<meta property="og:image:type" content="image/jpeg">', false)
            ->assertSee('<meta property="og:image:width" content="1200">', false)
            ->assertSee('<meta property="og:image:height" content="630">', false)
            ->assertSee('/storage/literasi/questions/legacy-question.png', false);

        $thumbnail = $this->get($thumbnailUrl)
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
        $size = getimagesize($thumbnail->baseResponse->getFile()->getPathname());
        $this->assertSame(1200, $size[0] ?? null);
        $this->assertSame(630, $size[1] ?? null);
    }

    public function test_public_literacy_material_without_image_uses_school_logo_for_social_thumbnail(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('site-branding/logo/logo-sma-afbs.png', $this->makeTestPng(400, 400));

        Pengaturan::query()->updateOrCreate(
            ['nama_pengaturan' => SiteSettingKeys::LOGO_PATH],
            ['nilai_pengaturan' => 'site-branding/logo/logo-sma-afbs.png'],
        );

        $material = $this->createMaterial('Materi Tanpa Gambar');
        $thumbnailUrl = route('library.literacy.social-thumbnail', [
            'slug' => $material->slug,
            'v' => $material->updated_at?->timestamp,
        ]);

        $this->get(route('library.literacy.show', $material->slug))
            ->assertOk()
            ->assertSee('<meta property="og:title" content="Materi Tanpa Gambar">', false)
            ->assertSee('<meta property="og:image" content="'.$thumbnailUrl.'">', false)
            ->assertSee('<meta name="twitter:image" content="'.$thumbnailUrl.'">', false);

        $this->get($thumbnailUrl)
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_public_literacy_reading_content_renders_rich_text_and_inline_images(): void
    {
        $material = $this->createMaterial('Materi Rich Text Literasi', [
            'reading_content' => '<h2>Judul Bagian</h2><p>Kalimat <strong>penting</strong> <span class="color" data-color="red">berwarna</span>.</p><img src="/storage/literasi/materials/reading/diagram.png" alt="Diagram bacaan">',
        ]);

        $this->get(route('library.literacy.show', $material->slug))
            ->assertOk()
            ->assertSee('literacy-reading-content', false)
            ->assertSee('<strong>penting</strong>', false)
            ->assertSee('/storage/literasi/materials/reading/diagram.png', false)
            ->assertSee('data-color="red"', false);

        $this->get(route('library.literacy.index'))
            ->assertOk()
            ->assertSee('Judul Bagian Kalimat penting berwarna.')
            ->assertDontSee('<strong>penting</strong>', false);
    }

    public function test_livewire_payload_depth_allows_rich_editor_text_color_marks(): void
    {
        $this->assertGreaterThanOrEqual(20, (int) config('livewire.payload.max_nesting_depth'));
    }

    public function test_student_can_submit_and_edit_literacy_answers_with_unique_code(): void
    {
        config()->set('literacy.submission_queue.enabled', false);

        $student = $this->createStudent('Codex Literasi Siswa A', 'XII IPA');
        $material = $this->createMaterial('Materi Jawaban Literasi');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Tuliskan ringkasan bacaan.',
            'min_characters' => 20,
            'max_characters' => 500,
        ]);

        $this->get(route('library.literacy.show', $material->slug))
            ->assertOk()
            ->assertSee('Materi Jawaban Literasi')
            ->assertSee('Codex Literasi Siswa A - XII IPA')
            ->assertSee('Tuliskan ringkasan bacaan.')
            ->assertSee('Ketik nama atau kelas siswa')
            ->assertSee('Verifikasi siswa')
            ->assertSee('NISN atau tanggal lahir')
            ->assertSee('Kode edit jawaban')
            ->assertSee('<strong class="font-semibold text-slate-900">Guru / Tim Literasi Numerasi</strong>', false)
            ->assertSee('Buka Edit')
            ->assertSee('data-literacy-student-combobox', false)
            ->assertSee('id="status-jawaban"', false)
            ->assertSee('data-literacy-scroll-target="#status-jawaban"', false)
            ->assertSee('data-literacy-answer-count', false)
            ->assertSee('data-literacy-queue-panel', false)
            ->assertSee('Menyiapkan jalur antrean')
            ->assertSee('data-literacy-ticket-endpoint', false)
            ->assertSee('data-literacy-queue-waited', false)
            ->assertSee('data-literacy-retry-statuses', false)
            ->assertSee('data-literacy-mass-mode="1"', false)
            ->assertSee('literacy.submission.draft.v2:', false)
            ->assertSee('Koneksi perangkat ke server terputus sementara')
            ->assertSee('Permintaan sedang dibatasi sementara (429)')
            ->assertSee('Layanan server belum dapat merespons')
            ->assertSee('Patokan status jawaban')
            ->assertSee('Struk dan kode edit sudah tampil')
            ->assertSee('Berhenti mencoba otomatis')
            ->assertDontSee('Server sedang ramai - tidak perlu menekan Kirim lagi')
            ->assertSee('Perbaiki jawaban')
            ->assertSee('literacyAnswerLimitValid', false)
            ->assertSee('aria-live="polite"', false);

        $this->from(route('library.literacy.show', $material->slug))
            ->get(route('library.literacy.edit.lookup', ['code' => 'SALAH1']))
            ->assertRedirect(route('library.literacy.show', $material->slug))
            ->assertSessionHasErrors(['code']);

        $this->post(route('library.literacy.store', $material->slug), [
            'student_id' => $student->getKey(),
            'answers' => [
                $question->getKey() => 'Saya memahami isi bacaan dan menemukan pesan utama tentang kebiasaan membaca setiap hari.',
            ],
        ])->assertRedirect();

        $response = PerpustakaanLiterasiResponse::query()
            ->where('data_siswa_id', $student->getKey())
            ->firstOrFail();

        $this->assertNotNull($response->edit_code);
        $this->assertSame(PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_DIRECT, $response->submission_delivery_code);
        $this->assertDatabaseHas('perpustakaan_literasi_answers', [
            'response_id' => $response->getKey(),
            'question_id' => $question->getKey(),
            'answer_text' => 'Saya memahami isi bacaan dan menemukan pesan utama tentang kebiasaan membaca setiap hari.',
        ]);

        $this->get(route('library.literacy.edit', $response->shortEditCode()))
            ->assertOk()
            ->assertSee($response->edit_code)
            ->assertSee('Saya memahami isi bacaan')
            ->assertSee('Isi Jawaban Murid Baru')
            ->assertSee(route('library.literacy.show', $material->slug), false)
            ->assertSee('id="status-jawaban"', false)
            ->assertSee('data-literacy-scroll-target="#status-jawaban"', false)
            ->assertSee('data-literacy-answer-count', false);

        $this->post(route('library.literacy.update', $response->shortEditCode()), [
            'answers' => [
                $question->getKey() => 'Jawaban saya sudah diedit dengan tambahan refleksi setelah membaca ulang materi.',
            ],
        ])->assertRedirect(route('library.literacy.completed'));

        $this->assertDatabaseHas('perpustakaan_literasi_answers', [
            'response_id' => $response->getKey(),
            'question_id' => $question->getKey(),
            'answer_text' => 'Jawaban saya sudah diedit dengan tambahan refleksi setelah membaca ulang materi.',
        ]);
    }

    public function test_student_identity_verification_is_required_when_master_data_has_nisn_or_birth_date(): void
    {
        $student = $this->createStudent('Codex Verifikasi Siswa', 'X Verifikasi', [
            'nisn' => '1234567890',
            'tanggal_lahir' => '2010-01-15',
        ]);
        $material = $this->createMaterial('Materi Verifikasi Siswa');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Tuliskan komitmen mengerjakan sendiri.',
            'min_characters' => 20,
            'max_characters' => 500,
        ]);

        $payload = [
            'student_id' => $student->getKey(),
            'answers' => [
                $question->getKey() => 'Saya mengerjakan soal ini sendiri dan tidak memakai nama siswa lain.',
            ],
        ];

        $this->post(route('library.literacy.store', $material->slug), $payload)
            ->assertSessionHasErrors(['student_verification']);

        $this->post(route('library.literacy.store', $material->slug), $payload + [
            'student_verification' => '0000000000',
        ])->assertSessionHasErrors(['student_verification']);

        $this->post(route('library.literacy.store', $material->slug), $payload + [
            'student_verification' => '15/01/2010',
        ])->assertRedirect();

        $this->assertDatabaseHas('perpustakaan_literasi_responses', [
            'data_siswa_id' => $student->getKey(),
            'student_name_snapshot' => 'Codex Verifikasi Siswa',
        ]);
    }

    public function test_student_identity_verification_can_be_disabled_per_material(): void
    {
        $student = $this->createStudent('Codex Verifikasi Opsional', 'X Opsional', [
            'nisn' => '9988776655',
            'tanggal_lahir' => '2010-02-20',
        ]);
        $material = $this->createMaterial('Materi Verifikasi Dimatikan', [
            'student_verification_enabled' => false,
        ]);
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Tuliskan ringkasan singkat.',
            'min_characters' => 20,
            'max_characters' => 500,
        ]);

        $this->get(route('library.literacy.show', $material->slug))
            ->assertOk()
            ->assertDontSee('Verifikasi siswa')
            ->assertDontSee('id="student_verification"', false);

        $this->post(route('library.literacy.store', $material->slug), [
            'student_id' => $student->getKey(),
            'answers' => [
                $question->getKey() => 'Saya mengerjakan soal ini dengan nama sendiri tanpa verifikasi tambahan.',
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('perpustakaan_literasi_responses', [
            'material_id' => $material->getKey(),
            'data_siswa_id' => $student->getKey(),
            'student_name_snapshot' => 'Codex Verifikasi Opsional',
        ]);
    }

    public function test_literacy_submission_queue_admits_fifteen_and_promotes_waiting_tickets_fifo(): void
    {
        config(['literacy.submission_queue.active_slots' => 15]);

        $material = $this->createMaterial('Materi Antrean FIFO');
        $queue = app(LiteracySubmissionQueue::class);
        $requests = [];
        $tickets = [];

        foreach (range(1, 17) as $index) {
            $requests[$index] = $this->literacyQueueRequest('browser-'.$index);
            $tickets[$index] = $queue->requestNewTicket(
                $requests[$index],
                $material,
                $index,
                (string) Str::uuid(),
            );
        }

        $this->assertSame(15, PerpustakaanLiterasiSubmissionTicket::query()->where('status', 'admitted')->count());
        $this->assertSame('waiting', $tickets[16]->refresh()->status);
        $this->assertSame('waiting', $tickets[17]->refresh()->status);
        $this->assertSame(1, $queue->payloadFor($tickets[16])['position']);
        $this->assertSame(2, $queue->payloadFor($tickets[17])['position']);

        $tickets[1]->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->assertSame('admitted', $queue->status($requests[16], $tickets[16]->public_token)['status']);
        $this->assertSame('expired', $tickets[1]->refresh()->status);
        $this->assertSame('waiting', $tickets[17]->refresh()->status);

        $queue->complete($tickets[2]);

        $this->assertSame('admitted', $queue->status($requests[17], $tickets[17]->public_token)['status']);
    }

    public function test_literacy_submission_queue_uses_adaptive_polling_and_sixty_second_admission(): void
    {
        $this->assertSame(10, config('literacy.submission_queue.active_slots'));

        config([
            'literacy.submission_queue.active_slots' => 1,
            'literacy.submission_queue.poll_seconds' => 5,
            'literacy.submission_queue.poll_middle_position' => 1,
            'literacy.submission_queue.poll_middle_seconds' => 12,
            'literacy.submission_queue.poll_far_position' => 2,
            'literacy.submission_queue.poll_far_seconds' => 25,
            'literacy.submission_queue.admission_ttl_seconds' => 60,
        ]);

        $material = $this->createMaterial('Materi Antrean Adaptif');
        $queue = app(LiteracySubmissionQueue::class);
        $tickets = [];

        foreach (range(1, 4) as $index) {
            $tickets[$index] = $queue->requestNewTicket(
                $this->literacyQueueRequest('adaptive-browser-'.$index),
                $material,
                $index,
                (string) Str::uuid(),
            );
        }

        $this->assertSame('admitted', $tickets[1]->refresh()->status);
        $this->assertGreaterThanOrEqual(58, now()->diffInSeconds($tickets[1]->expires_at, false));
        $this->assertLessThanOrEqual(60, now()->diffInSeconds($tickets[1]->expires_at, false));
        $this->assertSame(5, $queue->payloadFor($tickets[2])['retry_after_seconds']);
        $this->assertSame(12, $queue->payloadFor($tickets[3])['retry_after_seconds']);
        $this->assertSame(25, $queue->payloadFor($tickets[4])['retry_after_seconds']);
    }

    public function test_one_hundred_sixty_queue_requests_keep_ten_active_slots_and_fifo_order(): void
    {
        config(['literacy.submission_queue.active_slots' => 10]);

        $material = $this->createMaterial('Materi Simulasi 160 Tiket');
        $queue = app(LiteracySubmissionQueue::class);
        $tickets = collect(range(1, 160))->mapWithKeys(function (int $index) use ($queue, $material): array {
            $ticket = $queue->requestNewTicket(
                $this->literacyQueueRequest('mass-browser-'.$index),
                $material,
                $index,
                (string) Str::uuid(),
            );

            return [$index => $ticket];
        });

        $this->assertSame(10, PerpustakaanLiterasiSubmissionTicket::query()->where('status', 'admitted')->count());
        $this->assertSame(150, PerpustakaanLiterasiSubmissionTicket::query()->where('status', 'waiting')->count());
        $this->assertSame(
            $tickets->slice(10)->pluck('id')->take(10)->values()->all(),
            PerpustakaanLiterasiSubmissionTicket::query()
                ->where('status', 'waiting')
                ->orderBy('requested_at')
                ->orderBy('id')
                ->limit(10)
                ->pluck('id')
                ->all(),
        );

        $tickets->take(10)->each(fn (PerpustakaanLiterasiSubmissionTicket $ticket) => $queue->complete($ticket));

        $this->assertSame(10, PerpustakaanLiterasiSubmissionTicket::query()->where('status', 'admitted')->count());
        $this->assertSame(
            $tickets->slice(10)->pluck('id')->take(10)->values()->all(),
            PerpustakaanLiterasiSubmissionTicket::query()->where('status', 'admitted')->orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_similarity_worker_waits_until_submission_activity_is_idle(): void
    {
        config(['literacy.submission_queue.analysis_idle_seconds' => 180]);

        $material = $this->createMaterial('Materi Jeda Analisa');
        $queue = app(LiteracySubmissionQueue::class);
        $ticket = $queue->requestNewTicket(
            $this->literacyQueueRequest('analysis-idle-browser'),
            $material,
            1,
            (string) Str::uuid(),
        );

        $this->assertTrue($queue->analysisShouldWait());

        $ticket->forceFill([
            'status' => 'admitted',
            'expires_at' => now()->subSecond(),
        ])->save();
        PerpustakaanLiterasiSubmissionQueueState::query()
            ->whereKey(LiteracySubmissionQueue::SCOPE)
            ->update(['last_submission_activity_at' => now()->subSeconds(181)]);

        $this->assertFalse($queue->analysisShouldWait());

        PerpustakaanLiterasiSubmissionQueueState::query()
            ->whereKey(LiteracySubmissionQueue::SCOPE)
            ->update(['last_submission_activity_at' => now()]);

        $this->assertTrue($queue->analysisShouldWait());
    }

    public function test_json_submit_returns_redirect_for_automatic_retry(): void
    {
        Queue::fake();

        $student = $this->createStudent('Codex Retry JSON', 'X Retry');
        $material = $this->createMaterial('Materi Retry JSON', [
            'student_verification_enabled' => false,
        ]);
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jelaskan manfaat retry otomatis.',
            'min_characters' => 20,
            'max_characters' => 500,
        ]);
        $requestId = (string) Str::uuid();
        $payload = [
            'student_id' => $student->getKey(),
            'submission_request_id' => $requestId,
            'answers' => [
                $question->getKey() => 'Retry otomatis menjaga jawaban tetap aman ketika koneksi sekolah terputus sesaat.',
            ],
        ];

        $this->get(route('library.literacy.show', $material->slug))->assertOk();
        $ticket = $this->postJson(route('library.literacy.queue.store', $material->slug), [
            'student_id' => $student->getKey(),
            'submission_request_id' => $requestId,
        ])->assertCreated()->json('ticket');
        $payload['submission_ticket'] = $ticket;
        $payload['submission_queue_waited'] = '1';
        $payload['submission_retry_statuses'] = '429,503';

        $this->postJson(route('library.literacy.store', $material->slug), $payload)
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonStructure(['redirect_url', 'edit_code']);

        $this->assertSame(1, PerpustakaanLiterasiResponse::query()
            ->where('material_id', $material->getKey())
            ->where('data_siswa_id', $student->getKey())
            ->count());
        $response = PerpustakaanLiterasiResponse::query()
            ->where('material_id', $material->getKey())
            ->where('data_siswa_id', $student->getKey())
            ->firstOrFail();
        $this->assertSame(PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_RETRY_503, $response->submission_delivery_code);
        $this->assertGreaterThanOrEqual(1, $response->submission_queue_wait_seconds);
        $this->assertSame(['429', '503'], $response->submission_retry_statuses);
    }

    public function test_completed_submission_ticket_is_idempotent_for_the_same_browser(): void
    {
        $material = $this->createMaterial('Materi Tiket Idempoten');
        $queue = app(LiteracySubmissionQueue::class);
        $request = $this->literacyQueueRequest('idempotent-browser');
        $requestId = (string) Str::uuid();
        $ticket = $queue->requestNewTicket($request, $material, 123, $requestId);

        $claimed = $queue->claimNewSubmission(
            $request,
            $material,
            123,
            $requestId,
            $ticket->public_token,
        );

        $queue->complete($claimed);

        $retried = $queue->claimNewSubmission(
            $request,
            $material,
            123,
            $requestId,
            $ticket->public_token,
        );

        $this->assertSame($ticket->getKey(), $retried->getKey());
        $this->assertSame('completed', $retried->status);
        $this->assertSame(1, PerpustakaanLiterasiSubmissionTicket::query()->count());
    }

    public function test_async_verification_failure_returns_one_actionable_json_response(): void
    {
        $student = $this->createStudent('Codex Verifikasi Async', 'XI Async', [
            'nisn' => '1234509876',
            'tanggal_lahir' => '2010-04-17',
        ]);
        $material = $this->createMaterial('Materi Verifikasi Async');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Tuliskan komitmen belajar.',
            'min_characters' => 10,
            'max_characters' => 500,
        ]);
        $requestId = (string) Str::uuid();

        $this->withSession(['literacy_test_browser' => 'verification-async'])
            ->get(route('library.literacy.show', $material->slug))->assertOk();
        $ticket = $this->withHeaders(['X-Literacy-Client' => 'async-v2'])
            ->postJson(route('library.literacy.queue.store', $material->slug), [
                'student_id' => $student->getKey(),
                'submission_request_id' => $requestId,
            ])->assertCreated()->json('ticket');

        $this->withHeaders(['X-Literacy-Client' => 'async-v2'])
            ->post(route('library.literacy.store', $material->slug), [
                'student_id' => $student->getKey(),
                'student_verification' => '0000000000',
                'submission_request_id' => $requestId,
                'submission_ticket' => $ticket,
                'answers' => [
                    $question->getKey() => 'Saya akan mengerjakan soal dengan teliti.',
                ],
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertHeader('X-Literacy-Protocol', '2')
            ->assertJsonPath('status', 'verification_mismatch')
            ->assertJsonStructure(['errors' => ['student_verification']]);

        $this->assertDatabaseCount('perpustakaan_literasi_responses', 0);
        $this->assertDatabaseHas('perpustakaan_literasi_submission_tickets', [
            'public_token' => $ticket,
            'status' => PerpustakaanLiterasiSubmissionTicket::STATUS_CANCELLED,
        ]);
        $this->assertDatabaseHas('perpustakaan_literasi_submission_events', [
            'event_code' => 'submission_rejected',
            'data_siswa_id' => $student->getKey(),
            'http_status' => 422,
        ]);
    }

    public function test_async_existing_and_trashed_responses_return_specific_conflicts(): void
    {
        $student = $this->createStudent('Codex Konflik Async', 'XI Konflik');
        $material = $this->createMaterial('Materi Konflik Async', [
            'student_verification_enabled' => false,
        ]);
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jelaskan pelajaran hari ini.',
            'min_characters' => 10,
            'max_characters' => 500,
        ]);
        $response = $this->createResponseWithAnswer(
            $material,
            $student,
            $question,
            'Jawaban yang telah disimpan sebelumnya.',
        );

        $this->withSession(['literacy_test_browser' => 'response-conflicts'])
            ->get(route('library.literacy.show', $material->slug))->assertOk();

        foreach (['already_submitted', 'response_in_trash'] as $expectedStatus) {
            $requestId = (string) Str::uuid();
            $ticket = $this->withHeaders(['X-Literacy-Client' => 'async-v2'])
                ->postJson(route('library.literacy.queue.store', $material->slug), [
                    'student_id' => $student->getKey(),
                    'submission_request_id' => $requestId,
                ])->assertCreated()->json('ticket');

            $this->withHeaders(['X-Literacy-Client' => 'async-v2'])
                ->postJson(route('library.literacy.store', $material->slug), [
                    'student_id' => $student->getKey(),
                    'submission_request_id' => $requestId,
                    'submission_ticket' => $ticket,
                    'answers' => [
                        $question->getKey() => 'Jawaban kedua tidak boleh membuat respons ganda.',
                    ],
                ])
                ->assertStatus(409)
                ->assertJsonPath('status', $expectedStatus);

            if ($expectedStatus === 'already_submitted') {
                $response->delete();
            }
        }

        $this->assertSame(1, PerpustakaanLiterasiResponse::withTrashed()->count());
    }

    public function test_completed_ticket_recovers_receipt_without_reposting_answers(): void
    {
        Queue::fake();

        $student = $this->createStudent('Codex Pulih Struk', 'XI Pulih');
        $material = $this->createMaterial('Materi Pulih Struk', [
            'student_verification_enabled' => false,
        ]);
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jelaskan manfaat pemulihan struk.',
            'min_characters' => 10,
            'max_characters' => 500,
        ]);
        $requestId = (string) Str::uuid();

        $this->withSession(['literacy_test_browser' => 'receipt-recovery'])
            ->get(route('library.literacy.show', $material->slug))->assertOk();
        $ticket = $this->withHeaders(['X-Literacy-Client' => 'async-v2'])
            ->postJson(route('library.literacy.queue.store', $material->slug), [
                'student_id' => $student->getKey(),
                'submission_request_id' => $requestId,
            ])->assertCreated()->json('ticket');

        $this->withHeaders(['X-Literacy-Client' => 'async-v2'])
            ->postJson(route('library.literacy.store', $material->slug), [
                'student_id' => $student->getKey(),
                'submission_request_id' => $requestId,
                'submission_ticket' => $ticket,
                'answers' => [
                    $question->getKey() => 'Pemulihan struk memastikan jawaban tidak perlu dikirim ulang.',
                ],
            ])->assertOk();

        $this->post(route('library.literacy.queue.receipt', $ticket), [
            'submission_request_id' => $requestId,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonStructure(['redirect_url']);

        $this->assertSame(1, PerpustakaanLiterasiResponse::query()->count());
        $this->assertDatabaseHas('perpustakaan_literasi_submission_events', [
            'event_code' => 'receipt_recovered',
            'data_siswa_id' => $student->getKey(),
        ]);
    }

    public function test_cancelled_ticket_is_recycled_for_the_same_request_id(): void
    {
        $material = $this->createMaterial('Materi Tiket Daur Ulang');
        $queue = app(LiteracySubmissionQueue::class);
        $request = $this->literacyQueueRequest('recycled-browser');
        $requestId = (string) Str::uuid();
        $ticket = $queue->requestNewTicket($request, $material, 321, $requestId);

        $queue->release($ticket);
        $recycled = $queue->requestNewTicket($request, $material, 321, $requestId);

        $this->assertSame($ticket->getKey(), $recycled->getKey());
        $this->assertSame(1, PerpustakaanLiterasiSubmissionTicket::query()->count());
        $this->assertNotNull($recycled->request_key_hash);
        $this->assertContains($recycled->status, [
            PerpustakaanLiterasiSubmissionTicket::STATUS_ADMITTED,
            PerpustakaanLiterasiSubmissionTicket::STATUS_WAITING,
        ]);
    }

    public function test_completed_queue_tickets_backfill_existing_response_delivery_status(): void
    {
        $student = $this->createStudent('Codex Backfill Submit', 'XI Backfill');
        $material = $this->createMaterial('Materi Backfill Submit');
        $response = PerpustakaanLiterasiResponse::query()->create([
            'material_id' => $material->getKey(),
            'data_siswa_id' => $student->getKey(),
            'student_name_snapshot' => $student->nama,
            'student_class_snapshot' => $student->rombel_saat_ini,
            'submitted_at' => now(),
        ]);

        PerpustakaanLiterasiSubmissionTicket::query()->create([
            'public_token' => Str::random(64),
            'scope' => LiteracySubmissionQueue::SCOPE,
            'owner_hash' => hash('sha256', 'backfill-owner'),
            'operation_key' => 'create:'.$material->getKey().':'.$student->getKey().':'.Str::uuid(),
            'operation' => 'create',
            'material_id' => $material->getKey(),
            'response_id' => null,
            'data_siswa_id' => $student->getKey(),
            'status' => PerpustakaanLiterasiSubmissionTicket::STATUS_COMPLETED,
            'requested_at' => now()->subSeconds(8),
            'admitted_at' => now(),
            'started_at' => now(),
            'completed_at' => now(),
            'expires_at' => now()->addDay(),
            'result_response_id' => $response->getKey(),
        ]);

        $migration = require database_path('migrations/2026_07_23_214500_backfill_literacy_submission_delivery_codes.php');
        $migration->up();

        $response->refresh();
        $this->assertSame(PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_QUEUED, $response->submission_delivery_code);
        $this->assertGreaterThanOrEqual(8, $response->submission_queue_wait_seconds);
    }

    public function test_submit_only_queues_similarity_analysis_instead_of_running_it_in_request(): void
    {
        Queue::fake();

        $student = $this->createStudent('Codex Queue Analisa', 'X Queue');
        $material = $this->createMaterial('Materi Queue Analisa');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jelaskan manfaat antrean.',
            'min_characters' => 20,
            'max_characters' => 500,
        ]);

        $this->post(route('library.literacy.store', $material->slug), [
            'student_id' => $student->getKey(),
            'submission_request_id' => (string) Str::uuid(),
            'answers' => [
                $question->getKey() => 'Antrean membuat penyimpanan jawaban tetap ringan saat banyak siswa mengirim bersamaan.',
            ],
        ])->assertRedirect();

        $response = PerpustakaanLiterasiResponse::query()->where('data_siswa_id', $student->getKey())->firstOrFail();

        $this->assertSame(PerpustakaanLiterasiResponse::SIMILARITY_STATUS_PENDING, $response->similarity_analysis_status);
        $this->assertSame(1, $response->similarity_analysis_version);
        $this->assertDatabaseCount('perpustakaan_literasi_similarity_matches', 0);
        Queue::assertPushedOn('literacy-analysis', AnalyzeLiteracyResponseSimilarity::class);
    }

    public function test_edit_queues_later_responses_for_similarity_refresh(): void
    {
        config()->set('literacy.submission_queue.enabled', false);
        Queue::fake();

        $material = $this->createMaterial('Materi Antrean Analisa Setelah Edit');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jelaskan perubahan jawaban.',
            'min_characters' => 1,
            'max_characters' => 500,
        ]);
        $response = $this->createResponseWithAnswer(
            $material,
            $this->createStudent('Codex Edit Analisa', 'XI 3'),
            $question,
            'Jawaban awal yang akan diperbarui oleh siswa.',
            now()->subMinute(),
        );

        $this->post(route('library.literacy.update', $response->shortEditCode()), [
            'submission_request_id' => (string) Str::uuid(),
            'answers' => [
                $question->getKey() => 'Jawaban baru yang membutuhkan analisa ulang respons setelahnya.',
            ],
        ])->assertRedirect();

        Queue::assertPushedOn('literacy-analysis', AnalyzeLiteracyResponseSimilarity::class);
        Queue::assertPushedOn(
            'literacy-analysis',
            QueueLiteracySimilarityReanalysis::class,
            fn (QueueLiteracySimilarityReanalysis $job): bool => $job->materialId === $material->getKey()
                && $job->afterResponseId === $response->getKey(),
        );
    }

    public function test_one_hundred_sixty_student_sessions_on_same_school_ip_are_not_throttled(): void
    {
        $limiter = RateLimiter::limiter('literacy_queue_ticket');

        $this->assertNotNull($limiter);

        foreach (range(1, 160) as $index) {
            $request = $this->literacyQueueRequest('school-browser-'.$index);
            $request->server->set('REMOTE_ADDR', '203.0.113.25');
            $limits = $limiter($request);

            foreach ($limits as $limit) {
                RateLimiter::hit($limit->key, $limit->decaySeconds);
                $this->assertFalse(RateLimiter::tooManyAttempts($limit->key, $limit->maxAttempts));
            }
        }
    }

    public function test_public_form_renders_three_question_types_and_speech_fallback(): void
    {
        $this->createStudent('Codex Tiga Jenis', 'XI 1');
        $material = $this->createMaterial('Materi Tiga Jenis Soal');
        $material->questions()->create([
            'sort_order' => 1,
            'question_type' => PerpustakaanLiterasiQuestion::TYPE_ESSAY,
            'prompt' => "Jelaskan isi bacaan.\nGunakan bahasa sendiri.",
            'speech_input_enabled' => true,
            'min_characters' => 10,
            'max_characters' => 500,
        ]);
        $material->questions()->create([
            'sort_order' => 2,
            'question_type' => PerpustakaanLiterasiQuestion::TYPE_TRUE_FALSE,
            'prompt' => 'Tentukan Benar atau Salah.',
            'configuration' => [
                'items' => [
                    ['id' => 'tf-a', 'statement' => 'Pernyataan pertama', 'correct' => true],
                    ['id' => 'tf-b', 'statement' => 'Pernyataan kedua', 'correct' => false],
                ],
            ],
        ]);
        $material->questions()->create([
            'sort_order' => 3,
            'question_type' => PerpustakaanLiterasiQuestion::TYPE_MATCHING,
            'prompt' => 'Jodohkan pasangan berikut.',
            'configuration' => [
                'left' => [
                    ['id' => 'left-a', 'label' => 'Satu', 'correct_target_id' => 'right-a'],
                    ['id' => 'left-b', 'label' => 'Dua', 'correct_target_id' => 'right-b'],
                ],
                'right' => [
                    ['id' => 'right-a', 'label' => 'Pertama'],
                    ['id' => 'right-b', 'label' => 'Kedua'],
                ],
            ],
        ]);

        $this->get(route('library.literacy.show', $material->slug))
            ->assertOk()
            ->assertSee('Esai / jawaban tertulis')
            ->assertSee('Tabel Benar / Salah')
            ->assertSee('Menjodohkan')
            ->assertSee('Pernyataan pertama')
            ->assertSee('Pilih pasangan...')
            ->assertSee('Klik item Kolom A, lalu klik jawabannya di Kolom B.')
            ->assertSee('Hapus semua garis')
            ->assertSee('data-literacy-matching-board', false)
            ->assertSee('data-literacy-matching-canvas', false)
            ->assertSee('new ResizeObserver', false)
            ->assertSee('Jawab dengan Suara')
            ->assertSee('window.webkitSpeechRecognition', false)
            ->assertSee("Jelaskan isi bacaan.\nGunakan bahasa sendiri.");
    }

    public function test_admin_objective_tables_preserve_stable_ids_and_canonical_configuration(): void
    {
        $canonical = [
            'question_type' => PerpustakaanLiterasiQuestion::TYPE_MATCHING,
            'configuration' => [
                'version' => 1,
                'left' => [
                    ['id' => 'left-a', 'label' => 'Indonesia', 'correct_target_id' => 'right-a'],
                    ['id' => 'left-b', 'label' => 'Jepang', 'correct_target_id' => 'right-b'],
                ],
                'right' => [
                    ['id' => 'right-a', 'label' => 'Jakarta'],
                    ['id' => 'right-b', 'label' => 'Tokyo'],
                ],
            ],
        ];

        $formData = PerpustakaanLiterasiMaterialResource::prepareQuestionDataForForm($canonical);

        $this->assertSame([
            [
                'left_id' => 'left-a',
                'left_label' => 'Indonesia',
                'right_id' => 'right-a',
                'right_label' => 'Jakarta',
            ],
            [
                'left_id' => 'left-b',
                'left_label' => 'Jepang',
                'right_id' => 'right-b',
                'right_label' => 'Tokyo',
            ],
        ], data_get($formData, 'configuration.pairs'));

        $persisted = PerpustakaanLiterasiMaterialResource::prepareQuestionDataForPersistence($formData);

        $this->assertSame($canonical['configuration'], $persisted['configuration']);

        $trueFalseForm = PerpustakaanLiterasiMaterialResource::prepareQuestionDataForForm([
            'question_type' => PerpustakaanLiterasiQuestion::TYPE_TRUE_FALSE,
            'configuration' => [
                'items' => [
                    ['id' => 'tf-true', 'statement' => 'Benar', 'correct' => true],
                    ['id' => 'tf-false', 'statement' => 'Salah', 'correct' => false],
                ],
            ],
        ]);

        $this->assertSame('1', data_get($trueFalseForm, 'configuration.items.0.correct'));
        $this->assertSame('0', data_get($trueFalseForm, 'configuration.items.1.correct'));

        $trueFalsePersisted = PerpustakaanLiterasiMaterialResource::prepareQuestionDataForPersistence($trueFalseForm);

        $this->assertTrue(data_get($trueFalsePersisted, 'configuration.items.0.correct'));
        $this->assertFalse(data_get($trueFalsePersisted, 'configuration.items.1.correct'));
        $this->assertCount(
            2,
            data_get(
                PerpustakaanLiterasiMaterialResource::defaultQuestionConfiguration(
                    PerpustakaanLiterasiQuestion::TYPE_MATCHING,
                ),
                'pairs',
            ),
        );
    }

    public function test_admin_can_create_matching_question_from_two_column_table(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = User::query()->create([
            'name' => 'Admin Tabel Menjodohkan',
            'username' => 'admin-tabel-menjodohkan',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(CreatePerpustakaanLiterasiMaterial::class)
            ->fillForm([
                'title' => 'Materi Tabel Menjodohkan',
                'program_category' => PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE,
                'reading_content' => 'Bacaan untuk menguji editor tabel dua kolom.',
                'is_active' => true,
                'questions' => [
                    [
                        'question_type' => PerpustakaanLiterasiQuestion::TYPE_MATCHING,
                        'prompt' => 'Hubungkan negara dan ibu kotanya.',
                        'configuration' => [
                            'pairs' => [
                                [
                                    'left_id' => 'left-indonesia',
                                    'left_label' => 'Indonesia',
                                    'right_id' => 'right-jakarta',
                                    'right_label' => 'Jakarta',
                                ],
                                [
                                    'left_id' => 'left-jepang',
                                    'left_label' => 'Jepang',
                                    'right_id' => 'right-tokyo',
                                    'right_label' => 'Tokyo',
                                ],
                            ],
                        ],
                        'is_required' => true,
                    ],
                    [
                        'question_type' => PerpustakaanLiterasiQuestion::TYPE_TRUE_FALSE,
                        'prompt' => 'Tentukan kebenaran pernyataan berikut.',
                        'configuration' => [
                            'items' => [
                                [
                                    'id' => 'tf-jakarta',
                                    'statement' => 'Jakarta adalah ibu kota Indonesia.',
                                    'correct' => '1',
                                ],
                                [
                                    'id' => 'tf-tokyo',
                                    'statement' => 'Tokyo berada di Indonesia.',
                                    'correct' => '0',
                                ],
                            ],
                        ],
                        'is_required' => true,
                    ],
                ],
            ])
            ->assertSee('Kolom A · Pernyataan')
            ->assertSee('Kolom B · Kunci Jawaban')
            ->assertSee('Kolom A · Soal')
            ->assertSee('Kolom B · Jawaban')
            ->call('create')
            ->assertHasNoFormErrors();

        $material = PerpustakaanLiterasiMaterial::query()
            ->where('title', 'Materi Tabel Menjodohkan')
            ->firstOrFail();
        $question = $material
            ->questions()
            ->where('question_type', PerpustakaanLiterasiQuestion::TYPE_MATCHING)
            ->firstOrFail();
        $trueFalseQuestion = $material
            ->questions()
            ->where('question_type', PerpustakaanLiterasiQuestion::TYPE_TRUE_FALSE)
            ->firstOrFail();

        $this->assertSame('left-indonesia', data_get($question->configuration, 'left.0.id'));
        $this->assertSame('right-jakarta', data_get($question->configuration, 'left.0.correct_target_id'));
        $this->assertSame('Jakarta', data_get($question->configuration, 'right.0.label'));
        $this->assertTrue(data_get($trueFalseQuestion->configuration, 'items.0.correct'));
        $this->assertFalse(data_get($trueFalseQuestion->configuration, 'items.1.correct'));
    }

    public function test_objective_answers_are_validated_stored_and_scored_per_item(): void
    {
        Queue::fake();

        $student = $this->createStudent('Codex Nilai Objektif', 'XI 2');
        $material = $this->createMaterial('Materi Nilai Objektif');
        $trueFalse = $material->questions()->create([
            'sort_order' => 1,
            'question_type' => PerpustakaanLiterasiQuestion::TYPE_TRUE_FALSE,
            'prompt' => 'Pilih Benar atau Salah.',
            'configuration' => [
                'items' => [
                    ['id' => 'tf-1', 'statement' => 'Langit terlihat biru', 'correct' => true],
                    ['id' => 'tf-2', 'statement' => 'Dua tambah dua sama dengan lima', 'correct' => false],
                ],
            ],
        ]);
        $matching = $material->questions()->create([
            'sort_order' => 2,
            'question_type' => PerpustakaanLiterasiQuestion::TYPE_MATCHING,
            'prompt' => 'Pilih pasangan.',
            'configuration' => [
                'left' => [
                    ['id' => 'left-1', 'label' => 'Indonesia', 'correct_target_id' => 'right-1'],
                    ['id' => 'left-2', 'label' => 'Jepang', 'correct_target_id' => 'right-2'],
                ],
                'right' => [
                    ['id' => 'right-1', 'label' => 'Jakarta'],
                    ['id' => 'right-2', 'label' => 'Tokyo'],
                ],
            ],
        ]);

        $this->post(route('library.literacy.store', $material->slug), [
            'student_id' => $student->getKey(),
            'submission_request_id' => (string) Str::uuid(),
            'answers' => [
                $trueFalse->getKey() => [
                    'items' => ['tf-1' => '1', 'tf-2' => '1'],
                ],
                $matching->getKey() => [
                    'pairs' => ['left-1' => 'right-1', 'left-2' => 'right-2'],
                ],
            ],
        ])->assertRedirect();

        $response = PerpustakaanLiterasiResponse::query()
            ->where('data_siswa_id', $student->getKey())
            ->with('answers')
            ->firstOrFail();
        $trueFalseAnswer = $response->answers->firstWhere('question_id', $trueFalse->getKey());
        $matchingAnswer = $response->answers->firstWhere('question_id', $matching->getKey());

        $this->assertSame(1, $trueFalseAnswer->score_earned);
        $this->assertSame(2, $trueFalseAnswer->score_possible);
        $this->assertFalse($trueFalseAnswer->is_correct);
        $this->assertSame('automatic', $trueFalseAnswer->grading_source);
        $this->assertSame(['tf-1' => true, 'tf-2' => true], $trueFalseAnswer->answer_payload['items']);
        $this->assertSame(2, $matchingAnswer->score_earned);
        $this->assertSame(2, $matchingAnswer->score_possible);
        $this->assertTrue($matchingAnswer->is_correct);
        Queue::assertPushedOn('literacy-analysis', AnalyzeLiteracyResponseSimilarity::class);
    }

    public function test_matching_rejects_duplicate_or_manipulated_targets(): void
    {
        Queue::fake();

        $student = $this->createStudent('Codex Pasangan Ganda', 'XI 3');
        $material = $this->createMaterial('Materi Validasi Pasangan');
        $matching = $material->questions()->create([
            'sort_order' => 1,
            'question_type' => PerpustakaanLiterasiQuestion::TYPE_MATCHING,
            'prompt' => 'Jodohkan.',
            'configuration' => [
                'left' => [
                    ['id' => 'left-1', 'label' => 'A', 'correct_target_id' => 'right-1'],
                    ['id' => 'left-2', 'label' => 'B', 'correct_target_id' => 'right-2'],
                ],
                'right' => [
                    ['id' => 'right-1', 'label' => 'Satu'],
                    ['id' => 'right-2', 'label' => 'Dua'],
                ],
            ],
        ]);

        $this->from(route('library.literacy.show', $material->slug))
            ->post(route('library.literacy.store', $material->slug), [
                'student_id' => $student->getKey(),
                'submission_request_id' => (string) Str::uuid(),
                'answers' => [
                    $matching->getKey() => [
                        'pairs' => ['left-1' => 'right-1', 'left-2' => 'right-1'],
                    ],
                ],
            ])
            ->assertRedirect(route('library.literacy.show', $material->slug))
            ->assertSessionHasErrors('answers.'.$matching->getKey());

        $this->assertDatabaseMissing('perpustakaan_literasi_responses', [
            'data_siswa_id' => $student->getKey(),
        ]);
    }

    public function test_objective_answers_are_never_compared_as_plagiarism(): void
    {
        Queue::fake();

        $studentA = $this->createStudent('Codex Objektif A', 'XI 4');
        $studentB = $this->createStudent('Codex Objektif B', 'XI 4');
        $material = $this->createMaterial('Materi Objektif Tanpa Plagiasi');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'question_type' => PerpustakaanLiterasiQuestion::TYPE_TRUE_FALSE,
            'prompt' => 'Pilih jawaban.',
            'plagiarism_detection_enabled' => true,
            'configuration' => [
                'items' => [
                    ['id' => 'tf-1', 'statement' => 'Pernyataan A', 'correct' => true],
                    ['id' => 'tf-2', 'statement' => 'Pernyataan B', 'correct' => false],
                ],
            ],
        ]);
        $payload = [
            'answers' => [
                $question->getKey() => [
                    'items' => ['tf-1' => '1', 'tf-2' => '0'],
                ],
            ],
        ];

        $this->post(route('library.literacy.store', $material->slug), $payload + [
            'student_id' => $studentA->getKey(),
            'submission_request_id' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->post(route('library.literacy.store', $material->slug), $payload + [
            'student_id' => $studentB->getKey(),
            'submission_request_id' => (string) Str::uuid(),
        ])->assertRedirect();

        $responseB = PerpustakaanLiterasiResponse::query()
            ->where('data_siswa_id', $studentB->getKey())
            ->firstOrFail();

        app(LiterasiSimilarityAnalyzer::class)->analyzeResponse($responseB);

        $this->assertFalse($question->fresh()->plagiarismDetectionEnabled());
        $this->assertDatabaseCount('perpustakaan_literasi_similarity_matches', 0);
    }

    public function test_edit_code_prefix_follows_material_program_category(): void
    {
        $cases = [
            [PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION, 'LHP'],
            [PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE, 'NEP'],
            [PerpustakaanLiterasiMaterial::CATEGORY_SIGAP_29_KARAKTER, 'SIGAP'],
        ];

        foreach ($cases as [$category, $prefix]) {
            $student = $this->createStudent('Codex Kode '.$prefix, 'X Kode');
            $material = $this->createMaterial('Materi Kode '.$prefix, [
                'program_category' => $category,
            ]);
            $question = $material->questions()->create([
                'sort_order' => 1,
                'prompt' => 'Tuliskan jawaban untuk kode '.$prefix.'.',
                'min_characters' => 20,
                'max_characters' => 500,
            ]);

            $this->post(route('library.literacy.store', $material->slug), [
                'student_id' => $student->getKey(),
                'answers' => [
                    $question->getKey() => 'Jawaban siswa untuk memastikan kode edit mengikuti kategori materi.',
                ],
            ])->assertRedirect();

            $response = PerpustakaanLiterasiResponse::query()
                ->where('material_id', $material->getKey())
                ->where('data_siswa_id', $student->getKey())
                ->firstOrFail();

            $this->assertStringStartsWith($prefix.'-', (string) $response->edit_code);

            $this->get(route('library.literacy.edit.lookup', ['code' => $response->edit_code]))
                ->assertRedirect(route('library.literacy.edit', $response->shortEditCode()));
        }
    }

    public function test_duplicate_student_submission_without_code_is_rejected(): void
    {
        $student = $this->createStudent('Codex Literasi Siswa B', 'XI IPS');
        $material = $this->createMaterial('Materi Anti Duplikat');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Apa pesan utama bacaan?',
            'max_characters' => 500,
        ]);

        $payload = [
            'student_id' => $student->getKey(),
            'answers' => [
                $question->getKey() => 'Jawaban pertama dari siswa untuk materi anti duplikat.',
            ],
        ];

        $this->post(route('library.literacy.store', $material->slug), $payload)
            ->assertRedirect();

        $this->post(route('library.literacy.store', $material->slug), $payload)
            ->assertSessionHasErrors([
                'student_id' => 'Siswa ini sudah mengirim jawaban. Gunakan kode unik untuk mengedit jawaban. Jika nama sudah mengisi dan lupa kode editnya, hubungi Guru / Tim Literasi Numerasi agar kode edit dicek.',
            ]);
    }

    public function test_trashed_student_response_is_reported_without_server_error(): void
    {
        $student = $this->createStudent('Codex Jawaban di Sampah', 'XI Sampah');
        $material = $this->createMaterial('Materi Jawaban di Sampah');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Apa pesan utama bacaan?',
            'max_characters' => 500,
        ]);
        $response = $this->createResponseWithAnswer(
            $material,
            $student,
            $question,
            'Jawaban lama yang sudah dipindahkan ke Sampah.'
        );
        $response->delete();

        $this->from(route('library.literacy.show', $material->slug))
            ->post(route('library.literacy.store', $material->slug), [
                'student_id' => $student->getKey(),
                'answers' => [
                    $question->getKey() => 'Jawaban baru yang tidak boleh memicu duplicate-key 500.',
                ],
            ])
            ->assertRedirect(route('library.literacy.show', $material->slug))
            ->assertSessionHasErrors([
                'student_id' => 'Jawaban siswa ini berada di Sampah. Hubungi Guru / Tim Literasi Numerasi untuk merestore jawaban lama atau menghapusnya permanen sebelum mengerjakan ulang.',
            ]);

        $this->assertSame(1, PerpustakaanLiterasiResponse::withTrashed()
            ->where('material_id', $material->getKey())
            ->where('data_siswa_id', $student->getKey())
            ->count());
    }

    public function test_literacy_integrity_counts_are_saved_from_submit_update_and_beacon(): void
    {
        $student = $this->createStudent('Codex Integritas Siswa', 'XI Integritas');
        $material = $this->createMaterial('Materi Integritas');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Apa komitmen pengerjaan mandiri?',
            'min_characters' => 10,
            'max_characters' => 500,
        ]);

        $this->post(route('library.literacy.store', $material->slug), [
            'student_id' => $student->getKey(),
            'answers' => [
                $question->getKey() => 'Saya mengerjakan sendiri dengan jujur dan tidak menyalin jawaban teman.',
            ],
            'integrity' => [
                'tab_switch_count' => 2,
                'app_hidden_count' => 1,
                'page_leave_attempt_count' => 1,
            ],
        ])->assertRedirect();

        $response = PerpustakaanLiterasiResponse::query()
            ->where('data_siswa_id', $student->getKey())
            ->firstOrFail();

        $this->assertSame(2, $response->tab_switch_count);
        $this->assertSame(1, $response->app_hidden_count);
        $this->assertSame(1, $response->page_leave_attempt_count);
        $this->assertNotNull($response->last_integrity_event_at);

        $this->post(route('library.literacy.update', $response->shortEditCode()), [
            'answers' => [
                $question->getKey() => 'Saya memperbaiki jawaban dengan tetap mengerjakan sendiri dan jujur.',
            ],
            'integrity' => [
                'tab_switch_count' => 1,
                'app_hidden_count' => 0,
                'page_leave_attempt_count' => 2,
            ],
        ])->assertRedirect(route('library.literacy.completed'));

        $response->refresh();
        $this->assertSame(3, $response->tab_switch_count);
        $this->assertSame(1, $response->app_hidden_count);
        $this->assertSame(3, $response->page_leave_attempt_count);

        $this->post(route('library.literacy.integrity', $response->shortEditCode()), [
            'integrity' => [
                'tab_switch_count' => 1,
                'app_hidden_count' => 1,
                'page_leave_attempt_count' => 0,
            ],
        ])->assertNoContent();

        $response->refresh();
        $this->assertSame(4, $response->tab_switch_count);
        $this->assertSame(2, $response->app_hidden_count);
        $this->assertSame(3, $response->page_leave_attempt_count);
    }

    public function test_answer_character_limit_is_validated(): void
    {
        $student = $this->createStudent('Codex Literasi Siswa C', 'X MIPA');
        $material = $this->createMaterial('Materi Batas Karakter');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jawab singkat.',
            'max_characters' => 20,
        ]);

        $validationResponse = $this->postJson(route('library.literacy.store', $material->slug), [
            'student_id' => $student->getKey(),
            'answers' => [
                $question->getKey() => str_repeat('a', 21),
            ],
        ]);

        $validationResponse
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['answers.'.$question->getKey()]);

        $this->assertSame(
            'Jawaban pertanyaan 1 maksimal 20 karakter. Saat ini jawaban Anda berisi 21 karakter.',
            $validationResponse->json('errors')['answers.'.$question->getKey()][0] ?? null,
        );
    }

    public function test_answer_minimum_character_limit_is_validated(): void
    {
        $student = $this->createStudent('Codex Literasi Siswa Min', 'X Bahasa');
        $material = $this->createMaterial('Materi Minimal Karakter');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jawab dengan refleksi lengkap.',
            'min_characters' => 40,
            'max_characters' => 200,
        ]);

        $this->get(route('library.literacy.show', $material->slug))
            ->assertOk()
            ->assertSee('Min. 40 karakter');

        $this->post(route('library.literacy.store', $material->slug), [
            'student_id' => $student->getKey(),
            'answers' => [
                $question->getKey() => 'Terlalu pendek.',
            ],
        ])->assertSessionHasErrors(['answers.'.$question->getKey()]);
    }

    public function test_public_question_keeps_line_breaks_and_images_open_in_preview(): void
    {
        $this->createStudent('Codex Tampilan Soal', 'XI 1');
        $material = $this->createMaterial('Materi Tampilan Soal');
        $material->questions()->create([
            'sort_order' => 1,
            'prompt' => "Baris pertama\nBaris kedua",
            'image_path' => 'literasi/questions/contoh.webp',
            'max_characters' => 1000,
        ]);

        $this->get(route('library.literacy.show', $material->slug))
            ->assertOk()
            ->assertSee('whitespace-pre-line', false)
            ->assertSee("Baris pertama\nBaris kedua")
            ->assertSee('data-literacy-image-open', false)
            ->assertSee('Ketuk gambar untuk memperbesar')
            ->assertSee('data-literacy-image-preview', false)
            ->assertSee('data-literacy-validation-for="answers.', false);
    }

    public function test_failed_answer_validation_is_recorded_without_answer_contents(): void
    {
        config()->set('literacy.submission_queue.enabled', false);
        $student = $this->createStudent('Codex Validasi Event', 'XI 2');
        $material = $this->createMaterial('Materi Validasi Event', [
            'student_verification_enabled' => false,
        ]);
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jawab maksimal lima karakter.',
            'min_characters' => 0,
            'max_characters' => 5,
            'is_required' => true,
        ]);

        $this->postJson(route('library.literacy.store', $material->slug), [
            'student_id' => $student->getKey(),
            'answers' => [$question->getKey() => 'jawaban-rahasia-siswa'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['answers.'.$question->getKey()]);

        $event = PerpustakaanLiterasiSubmissionEvent::query()->sole();
        $this->assertSame('validation_failed', $event->event_code);
        $this->assertSame(['answers.'.$question->getKey()], $event->context['fields']);
        $this->assertStringNotContainsString('jawaban-rahasia-siswa', json_encode($event->toArray()));
    }

    public function test_unexpected_success_payload_event_records_only_safe_diagnostics(): void
    {
        $student = $this->createStudent('Codex Pemulihan Struk', 'XI 2');
        $material = $this->createMaterial('Materi Pemulihan Struk', [
            'student_verification_enabled' => false,
        ]);

        $this->postJson(route('library.literacy.submission-event', $material->slug), [
            'event_code' => 'unexpected_success_payload',
            'submission_request_id' => (string) Str::uuid(),
            'retry_statuses' => '200',
            'http_status' => 200,
            'content_type' => 'text/html; charset=UTF-8',
            'payload_status' => 'completed',
            'answer' => 'isi-rahasia-yang-tidak-boleh-disimpan',
        ])->assertNoContent();

        $event = PerpustakaanLiterasiSubmissionEvent::query()->sole();
        $this->assertSame('unexpected_success_payload', $event->event_code);
        $this->assertSame(200, $event->http_status);
        $this->assertSame('text/html; charset=UTF-8', $event->context['content_type']);
        $this->assertSame('completed', $event->context['payload_status']);
        $this->assertSame('success_payload_missing_receipt_redirect', $event->context['reason']);
        $this->assertStringNotContainsString(
            'isi-rahasia-yang-tidak-boleh-disimpan',
            json_encode($event->toArray()),
        );
        $this->assertNull($event->data_siswa_id);
        $this->assertDatabaseHas('data_siswa', ['id' => $student->getKey()]);
    }

    public function test_browser_can_report_hosting_429_separately_from_application_throttle(): void
    {
        $material = $this->createMaterial('Materi Diagnostik Hosting 429');

        $this->postJson(route('library.literacy.submission-event', $material->slug), [
            'event_code' => 'hosting_throttled',
            'submission_request_id' => (string) Str::uuid(),
            'retry_statuses' => '429',
            'http_status' => 429,
        ])->assertNoContent();

        $event = PerpustakaanLiterasiSubmissionEvent::query()->sole();
        $this->assertSame('hosting_throttled', $event->event_code);
        $this->assertSame(429, $event->http_status);
        $this->assertSame('http_429_without_application_header', $event->context['reason']);
    }

    public function test_question_limit_cannot_be_lowered_below_saved_answer(): void
    {
        $student = $this->createStudent('Codex Batas Tersimpan', 'XI 3');
        $material = $this->createMaterial('Materi Batas Tersimpan');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Tuliskan esai.',
            'min_characters' => 0,
            'max_characters' => 1000,
        ]);
        $this->createResponseWithAnswer($material, $student, $question, str_repeat('a', 600));

        $this->expectException(ValidationException::class);
        $question->forceFill(['max_characters' => 500])->save();
    }

    public function test_school_network_monitor_requires_token_and_records_health_check(): void
    {
        config()->set('literacy.school_monitor.token', 'monitor-token-test');
        $payload = [
            'source' => 'school-test',
            'status' => 'ok',
            'dns_ok' => true,
            'tcp_ok' => true,
            'http_status' => 200,
            'duration_ms' => 245,
            'consecutive_failures' => 0,
            'checked_at' => now()->toIso8601String(),
            'context' => [
                'client_version' => 'test',
                'monitor_enabled' => false,
                'event_type' => 'state_change',
            ],
        ];

        $this->postJson(route('api.monitoring.school-network'), $payload)
            ->assertUnauthorized();

        $this->withToken('monitor-token-test')
            ->postJson(route('api.monitoring.school-network'), $payload)
            ->assertCreated()
            ->assertJson(['recorded' => true]);

        $check = PerpustakaanLiterasiNetworkCheck::query()->sole();
        $this->assertSame('school-test', $check->source);
        $this->assertTrue($check->dns_ok);
        $this->assertSame(200, $check->http_status);
        $this->assertFalse(data_get($check->context, 'monitor_enabled'));

        $health = app(LiteracyOperationalHealth::class)->snapshot();
        $this->assertSame('disabled', $health['network_monitor_state']);
    }

    public function test_public_literacy_page_includes_math_renderer_for_formula_content(): void
    {
        $this->createStudent('Codex Literasi Siswa Math', 'XII MIPA');
        $material = $this->createMaterial('Materi Rumus Matematika', [
            'reading_content' => 'Rumus luas: \(L = \pi r^2\). Pecahan: \(\frac{a}{b}\).',
        ]);
        $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Hitung nilai \(\sqrt{81}\) dan jelaskan langkahnya.',
            'max_characters' => 500,
        ]);

        $this->get(route('library.literacy.show', $material->slug))
            ->assertOk()
            ->assertSee('tex-chtml.js', false)
            ->assertSee('\(L', false)
            ->assertSee('\pi r^2\)', false)
            ->assertSee('\(\sqrt{81}\)', false);
    }

    public function test_similar_answers_create_and_refresh_plagiarism_matches(): void
    {
        $studentA = $this->createStudent('Codex Literasi Siswa D', 'XII IPA');
        $studentB = $this->createStudent('Codex Literasi Siswa E', 'XII IPA');
        $material = $this->createMaterial('Materi Analisa Plagiat');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jelaskan hasil bacaan.',
            'max_characters' => 1000,
        ]);

        $sameAnswer = 'Saya membaca materi dengan teliti dan memahami bahwa kebiasaan literasi perlu dilakukan setiap hari agar pengetahuan bertambah dan cara berpikir menjadi lebih runtut.';

        $this->post(route('library.literacy.store', $material->slug), [
            'student_id' => $studentA->getKey(),
            'answers' => [$question->getKey() => $sameAnswer],
        ])->assertRedirect();

        $this->post(route('library.literacy.store', $material->slug), [
            'student_id' => $studentB->getKey(),
            'answers' => [$question->getKey() => $sameAnswer],
        ])->assertRedirect();

        $responseB = PerpustakaanLiterasiResponse::query()
            ->where('data_siswa_id', $studentB->getKey())
            ->firstOrFail();

        $this->assertDatabaseHas('perpustakaan_literasi_similarity_matches', [
            'material_id' => $material->getKey(),
            'question_id' => $question->getKey(),
            'later_response_id' => $responseB->getKey(),
            'student_class_snapshot' => 'XII IPA',
        ]);

        $this->post(route('library.literacy.update', $responseB->shortEditCode()), [
            'answers' => [
                $question->getKey() => 'Saya membuat refleksi baru tentang tokoh, alur, dan manfaat membaca tanpa menyalin jawaban teman.',
            ],
        ])->assertRedirect();

        $this->assertDatabaseMissing('perpustakaan_literasi_similarity_matches', [
            'later_response_id' => $responseB->getKey(),
        ]);
    }

    public function test_answers_equal_to_answer_key_are_not_flagged_as_similarity(): void
    {
        $studentA = $this->createStudent('Codex Plagiasi Pendek A', 'X Pendek');
        $studentB = $this->createStudent('Codex Plagiasi Pendek B', 'X Pendek');
        $material = $this->createMaterial('Materi Plagiasi Jawaban Pendek');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jawab singkat.',
            'min_characters' => 1,
            'max_characters' => 50,
            'plagiarism_detection_enabled' => true,
            'answer_key' => 'Sama',
        ]);

        $this->post(route('library.literacy.store', $material->slug), [
            'student_id' => $studentA->getKey(),
            'answers' => [
                $question->getKey() => 'Sama',
            ],
        ])->assertRedirect();

        $this->post(route('library.literacy.store', $material->slug), [
            'student_id' => $studentB->getKey(),
            'answers' => [
                $question->getKey() => 'Sama',
            ],
        ])->assertRedirect();

        $responseB = PerpustakaanLiterasiResponse::query()
            ->where('data_siswa_id', $studentB->getKey())
            ->firstOrFail();
        $answerA = PerpustakaanLiterasiAnswer::query()
            ->whereHas('response', fn ($query) => $query->where('data_siswa_id', $studentA->getKey()))
            ->firstOrFail();
        $answerB = $responseB->answers()->firstOrFail();

        $this->assertTrue($answerA->is_correct);
        $this->assertTrue($answerB->is_correct);

        $this->assertDatabaseMissing('perpustakaan_literasi_similarity_matches', [
            'later_response_id' => $responseB->getKey(),
        ]);
    }

    public function test_similarity_keeps_one_strongest_earlier_match_per_answer(): void
    {
        $material = $this->createMaterial('Materi Satu Pembanding Terkuat');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jelaskan isi materi.',
            'min_characters' => 1,
            'max_characters' => 1000,
            'plagiarism_detection_enabled' => true,
        ]);
        $answerText = 'Renovasi gedung diselesaikan secara bertahap dengan perencanaan waktu tenaga dan bahan yang tertib.';
        $responseA = $this->createResponseWithAnswer(
            $material,
            $this->createStudent('Codex Pembanding Pertama', 'XI 1'),
            $question,
            $answerText,
            now()->subMinutes(3),
        );
        $responseB = $this->createResponseWithAnswer(
            $material,
            $this->createStudent('Codex Pembanding Kedua', 'XI 1'),
            $question,
            $answerText,
            now()->subMinutes(2),
        );
        $responseC = $this->createResponseWithAnswer(
            $material,
            $this->createStudent('Codex Pembanding Ketiga', 'XI 1'),
            $question,
            $answerText,
            now()->subMinute(),
        );
        $analyzer = app(LiterasiSimilarityAnalyzer::class);

        // Urutan job sengaja dibalik untuk memastikan hasil tidak bergantung urutan worker.
        $analyzer->analyzeResponse($responseC);
        $analyzer->analyzeResponse($responseB);
        $analyzer->analyzeResponse($responseA);
        $analyzer->analyzeResponse($responseC);

        $this->assertDatabaseCount('perpustakaan_literasi_similarity_matches', 2);
        $this->assertDatabaseHas('perpustakaan_literasi_similarity_matches', [
            'later_response_id' => $responseB->getKey(),
            'matched_response_id' => $responseA->getKey(),
            'similarity_score' => 100.00,
        ]);
        $this->assertDatabaseHas('perpustakaan_literasi_similarity_matches', [
            'later_response_id' => $responseC->getKey(),
            'matched_response_id' => $responseA->getKey(),
            'similarity_score' => 100.00,
        ]);
    }

    public function test_similarity_threshold_is_inclusive_at_eighty_percent(): void
    {
        $material = $this->createMaterial('Materi Ambang Kemiripan');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jelaskan hasil perhitungan.',
            'min_characters' => 1,
            'max_characters' => 1000,
            'plagiarism_detection_enabled' => true,
        ]);
        $responseA = $this->createResponseWithAnswer(
            $material,
            $this->createStudent('Codex Ambang A', 'XI 2'),
            $question,
            'Jawaban pertama memiliki susunan kata yang cukup panjang untuk dibandingkan oleh sistem secara aman.',
            now()->subMinute(),
        );
        $responseB = $this->createResponseWithAnswer(
            $material,
            $this->createStudent('Codex Ambang B', 'XI 2'),
            $question,
            'Jawaban kedua memiliki susunan kata yang cukup panjang untuk dibandingkan oleh sistem secara aman.',
            now(),
        );
        $answerB = $responseB->answers()->firstOrFail();
        $analyzer = new class extends LiterasiSimilarityAnalyzer
        {
            public float $forcedScore = 79.99;

            protected function similarityScore(string $left, string $right): float
            {
                return $this->forcedScore;
            }
        };

        $analyzer->analyzeResponse($responseB, 80);
        $this->assertDatabaseMissing('perpustakaan_literasi_similarity_matches', [
            'later_answer_id' => $answerB->getKey(),
        ]);

        $analyzer->forcedScore = 80.0;
        $analyzer->analyzeResponse($responseB, 80);
        $this->assertDatabaseHas('perpustakaan_literasi_similarity_matches', [
            'later_answer_id' => $answerB->getKey(),
            'matched_response_id' => $responseA->getKey(),
            'similarity_score' => 80.00,
        ]);
    }

    public function test_similarity_prefers_the_highest_score_over_the_oldest_candidate(): void
    {
        $material = $this->createMaterial('Materi Pembanding Paling Kuat');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jelaskan kesimpulan.',
            'min_characters' => 1,
            'max_characters' => 1000,
        ]);
        $responseA = $this->createResponseWithAnswer(
            $material,
            $this->createStudent('Codex Pembanding Lama', 'XI 4'),
            $question,
            'Pembanding lama berisi penjelasan panjang biasa untuk kebutuhan pengujian sistem kemiripan jawaban.',
            now()->subMinutes(3),
        );
        $responseB = $this->createResponseWithAnswer(
            $material,
            $this->createStudent('Codex Pembanding Kuat', 'XI 4'),
            $question,
            'Pembanding kuat berisi penjelasan panjang khusus untuk kebutuhan pengujian sistem kemiripan jawaban.',
            now()->subMinutes(2),
        );
        $responseC = $this->createResponseWithAnswer(
            $material,
            $this->createStudent('Codex Jawaban Terakhir', 'XI 4'),
            $question,
            'Jawaban terakhir berisi penjelasan panjang untuk kebutuhan pengujian sistem kemiripan jawaban.',
            now()->subMinute(),
        );
        $analyzer = new class extends LiterasiSimilarityAnalyzer
        {
            protected function similarityScore(string $left, string $right): float
            {
                return str_contains($right, 'pembanding kuat') ? 95.0 : 85.0;
            }
        };

        $analyzer->analyzeResponse($responseC, 80);

        $this->assertDatabaseHas('perpustakaan_literasi_similarity_matches', [
            'later_response_id' => $responseC->getKey(),
            'matched_response_id' => $responseB->getKey(),
            'similarity_score' => 95.00,
        ]);
        $this->assertDatabaseMissing('perpustakaan_literasi_similarity_matches', [
            'later_response_id' => $responseC->getKey(),
            'matched_response_id' => $responseA->getKey(),
        ]);
    }

    public function test_similarity_review_is_preserved_only_while_both_answers_are_unchanged(): void
    {
        $material = $this->createMaterial('Materi Review Kemiripan');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Tuliskan refleksi.',
            'min_characters' => 1,
            'max_characters' => 1000,
        ]);
        $answerText = 'Refleksi panjang yang sama dipakai untuk memastikan hasil review kemiripan tetap konsisten setelah analisa ulang.';
        $responseA = $this->createResponseWithAnswer($material, $this->createStudent('Codex Review A', 'X 2'), $question, $answerText, now()->subMinute());
        $responseB = $this->createResponseWithAnswer($material, $this->createStudent('Codex Review B', 'X 2'), $question, $answerText, now());
        $analyzer = app(LiterasiSimilarityAnalyzer::class);
        $analyzer->analyzeResponse($responseB);

        $reviewedAt = now()->addMinute();
        $match = PerpustakaanLiterasiSimilarityMatch::query()->sole();
        $match->forceFill([
            'review_status' => PerpustakaanLiterasiSimilarityMatch::REVIEW_CLEARED,
            'reviewed_at' => $reviewedAt,
        ])->save();

        $analyzer->analyzeResponse($responseB->fresh(['answers.question']));
        $this->assertSame(
            PerpustakaanLiterasiSimilarityMatch::REVIEW_CLEARED,
            PerpustakaanLiterasiSimilarityMatch::query()->sole()->review_status,
        );

        $answerB = $responseB->answers()->firstOrFail();
        $answerB->forceFill(['updated_at' => $reviewedAt->copy()->addMinute()])->saveQuietly();
        $analyzer->analyzeResponse($responseB->fresh(['answers.question']));

        $this->assertSame(
            PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED,
            PerpustakaanLiterasiSimilarityMatch::query()->sole()->review_status,
        );
    }

    public function test_answer_key_clamps_minimum_and_expands_maximum_character_limits(): void
    {
        $material = $this->createMaterial('Materi Batas Kunci Jawaban');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Berapa lama proyek selesai?',
            'answer_key' => '40 hari',
            'min_characters' => 20,
            'max_characters' => 5,
        ]);

        $this->assertSame(7, $question->fresh()->min_characters);
        $this->assertSame(7, $question->fresh()->max_characters);
        $this->assertSame(7, $question->minimumCharacters());
        $this->assertSame(7, $question->maximumCharacters());

        $this->get(route('library.literacy.show', $material->slug))
            ->assertOk()
            ->assertSee('Min. 7 karakter')
            ->assertSee('Maks. 7 karakter');
    }

    public function test_reconcile_command_dry_run_does_not_write_and_apply_reduces_matches(): void
    {
        $material = $this->createMaterial('Materi Rekonsiliasi Kemiripan');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Tuliskan penjelasan.',
            'min_characters' => 1,
            'max_characters' => 1000,
            'plagiarism_detection_enabled' => true,
        ]);
        $answerText = 'Penjelasan renovasi gedung dibuat teratur agar waktu pekerjaan tetap sesuai dengan rencana bersama.';
        $responseA = $this->createResponseWithAnswer($material, $this->createStudent('Codex Rekonsiliasi A', 'X 1'), $question, $answerText, now()->subMinutes(3));
        $responseB = $this->createResponseWithAnswer($material, $this->createStudent('Codex Rekonsiliasi B', 'X 1'), $question, $answerText, now()->subMinutes(2));
        $responseC = $this->createResponseWithAnswer($material, $this->createStudent('Codex Rekonsiliasi C', 'X 1'), $question, $answerText, now()->subMinute());
        $answerA = $responseA->answers()->firstOrFail();
        $answerB = $responseB->answers()->firstOrFail();
        $answerC = $responseC->answers()->firstOrFail();

        foreach ([[$responseC, $answerC, $responseA, $answerA], [$responseC, $answerC, $responseB, $answerB]] as [$laterResponse, $laterAnswer, $matchedResponse, $matchedAnswer]) {
            PerpustakaanLiterasiSimilarityMatch::query()->create([
                'material_id' => $material->getKey(),
                'question_id' => $question->getKey(),
                'later_response_id' => $laterResponse->getKey(),
                'matched_response_id' => $matchedResponse->getKey(),
                'later_answer_id' => $laterAnswer->getKey(),
                'matched_answer_id' => $matchedAnswer->getKey(),
                'student_class_snapshot' => 'X 1',
                'similarity_score' => 100,
                'later_submitted_at' => $laterResponse->submitted_at,
                'matched_submitted_at' => $matchedResponse->submitted_at,
            ]);
        }

        $this->artisan('literacy:similarity-reconcile', [
            '--material' => $material->getKey(),
            '--dry-run' => true,
        ])->assertSuccessful();
        $this->assertDatabaseCount('perpustakaan_literasi_similarity_matches', 2);

        $this->artisan('literacy:similarity-reconcile', [
            '--material' => $material->getKey(),
            '--apply' => true,
            '--batch' => 2,
        ])->assertSuccessful();
        $this->assertDatabaseCount('perpustakaan_literasi_similarity_matches', 2);
        $this->assertDatabaseHas('perpustakaan_literasi_similarity_matches', [
            'later_response_id' => $responseB->getKey(),
            'matched_response_id' => $responseA->getKey(),
        ]);
        $this->assertDatabaseHas('perpustakaan_literasi_similarity_matches', [
            'later_response_id' => $responseC->getKey(),
            'matched_response_id' => $responseA->getKey(),
        ]);
    }

    public function test_similarity_relation_manager_renders_compact_summary_cards(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::query()->create([
            'name' => 'Admin Ringkasan Kemiripan',
            'username' => 'admin-ringkasan-kemiripan',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');
        $material = $this->createMaterial('Materi Ringkasan Kemiripan');
        $summaryQueries = [];

        DB::listen(function ($query) use (&$summaryQueries): void {
            if (str_contains($query->sql, 'COUNT(DISTINCT later_response_id)')) {
                $summaryQueries[] = $query->sql;
            }
        });

        Livewire::actingAs($admin)
            ->test(SimilarityMatchesRelationManager::class, [
                'ownerRecord' => $material,
                'pageClass' => ViewPerpustakaanLiterasiMaterial::class,
            ])
            ->assertSee('Ringkasan pemeriksaan kemiripan')
            ->assertSee('Siswa terindikasi')
            ->assertSee('Jawaban terindikasi')
            ->assertSee('bukan vonis plagiasi');

        $this->assertNotEmpty($summaryQueries);
        $this->assertStringNotContainsString('order by', strtolower($summaryQueries[0]));
    }

    public function test_similarity_relation_manager_summary_counts_mixed_review_statuses(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::query()->create([
            'name' => 'Admin Status Kemiripan',
            'username' => 'admin-status-kemiripan',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');
        $material = $this->createMaterial('Materi Status Kemiripan');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jelaskan isi bacaan.',
            'min_characters' => 1,
            'max_characters' => 1000,
            'plagiarism_detection_enabled' => true,
        ]);
        $matchedResponse = $this->createResponseWithAnswer(
            $material,
            $this->createStudent('Codex Pembanding Ringkasan', 'X 1'),
            $question,
            'Jawaban pembanding untuk ringkasan.',
            now()->subMinutes(4),
        );
        $matchedAnswer = $matchedResponse->answers->first();

        foreach ([
            PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED,
            PerpustakaanLiterasiSimilarityMatch::REVIEW_CLEARED,
            PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED,
        ] as $index => $status) {
            $laterResponse = $this->createResponseWithAnswer(
                $material,
                $this->createStudent('Codex Terindikasi '.($index + 1), 'X 1'),
                $question,
                'Jawaban terindikasi untuk ringkasan '.($index + 1).'.',
                now()->subMinutes(3 - $index),
            );
            $laterAnswer = $laterResponse->answers->first();

            PerpustakaanLiterasiSimilarityMatch::query()->create([
                'material_id' => $material->getKey(),
                'question_id' => $question->getKey(),
                'later_response_id' => $laterResponse->getKey(),
                'matched_response_id' => $matchedResponse->getKey(),
                'later_answer_id' => $laterAnswer->getKey(),
                'matched_answer_id' => $matchedAnswer->getKey(),
                'student_class_snapshot' => 'X 1',
                'similarity_score' => 90 + $index,
                'later_submitted_at' => $laterResponse->submitted_at,
                'matched_submitted_at' => $matchedResponse->submitted_at,
                'review_status' => $status,
                'reviewed_by' => $status === PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED ? null : $admin->getKey(),
                'reviewed_at' => $status === PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED ? null : now(),
            ]);
        }

        $component = Livewire::actingAs($admin)->test(SimilarityMatchesRelationManager::class, [
            'ownerRecord' => $material,
            'pageClass' => ViewPerpustakaanLiterasiMaterial::class,
        ]);
        $summaryMethod = new \ReflectionMethod($component->instance(), 'similaritySummary');
        $summary = $summaryMethod->invoke($component->instance());

        $this->assertSame(3, $summary['students']);
        $this->assertSame(3, $summary['answers']);
        $this->assertSame(1, $summary['suspected']);
        $this->assertSame(1, $summary['cleared']);
        $this->assertSame(1, $summary['confirmed']);
    }

    public function test_answer_key_auto_grades_when_plagiarism_detection_is_disabled(): void
    {
        $studentA = $this->createStudent('Codex Kunci Benar A', 'X Kunci');
        $studentB = $this->createStudent('Codex Kunci Benar B', 'X Kunci');
        $studentC = $this->createStudent('Codex Kunci Belum Dinilai', 'X Kunci');
        $material = $this->createMaterial('Materi Kunci Jawaban');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Tuliskan kalimat kunci.',
            'min_characters' => 5,
            'max_characters' => 500,
            'plagiarism_detection_enabled' => false,
            'answer_key' => 'Literasi membentuk kebiasaan berpikir runtut.',
        ]);

        $this->post(route('library.literacy.store', $material->slug), [
            'student_id' => $studentA->getKey(),
            'answers' => [
                $question->getKey() => '  literasi membentuk kebiasaan berpikir runtut.  ',
            ],
        ])->assertRedirect();

        $this->post(route('library.literacy.store', $material->slug), [
            'student_id' => $studentB->getKey(),
            'answers' => [
                $question->getKey() => 'Literasi membentuk kebiasaan berpikir runtut.',
            ],
        ])->assertRedirect();

        $this->post(route('library.literacy.store', $material->slug), [
            'student_id' => $studentC->getKey(),
            'answers' => [
                $question->getKey() => 'Jawaban berbeda yang perlu diperiksa guru.',
            ],
        ])->assertRedirect();

        $correctAnswer = PerpustakaanLiterasiAnswer::query()
            ->whereHas('response', fn ($query) => $query->where('data_siswa_id', $studentA->getKey()))
            ->firstOrFail();
        $wrongAnswer = PerpustakaanLiterasiAnswer::query()
            ->whereHas('response', fn ($query) => $query->where('data_siswa_id', $studentC->getKey()))
            ->firstOrFail();

        $this->assertTrue($correctAnswer->is_correct);
        $this->assertNull($correctAnswer->graded_by);
        $this->assertNotNull($correctAnswer->graded_at);
        $this->assertSame('Dinilai otomatis berdasarkan kunci jawaban.', $correctAnswer->grading_note);
        $this->assertNull($wrongAnswer->is_correct);
        $this->assertNull($wrongAnswer->graded_at);
        $this->assertDatabaseCount('perpustakaan_literasi_similarity_matches', 0);
    }

    public function test_literacy_admin_access_uses_module_matrix(): void
    {
        $guru = User::query()->create([
            'name' => 'Guru Literasi',
            'username' => 'guru-literasi',
            'password' => bcrypt('password'),
            'module_access_levels' => AdminModuleAccess::normalizeLevels([
                'perpustakaan_literasi' => AdminModuleAccess::MANAGE,
            ]),
        ]);
        $guru->assignRole('guru');

        $limited = User::query()->create([
            'name' => 'Guru Terbatas',
            'username' => 'guru-terbatas-literasi',
            'password' => bcrypt('password'),
            'module_access_levels' => AdminModuleAccess::normalizeLevels([]),
        ]);
        $limited->assignRole('guru');

        $this->assertTrue(AdminModuleAccess::definitions()->contains('prefix', 'perpustakaan_literasi'));

        $this->actingAs($guru);
        $this->assertTrue(PerpustakaanLiterasiMaterialResource::canViewAny());
        $this->assertTrue(PerpustakaanLiterasiMaterialResource::canCreate());

        $this->actingAs($limited);
        $this->assertFalse(PerpustakaanLiterasiMaterialResource::canViewAny());
    }

    public function test_admin_create_form_hides_latex_picker_until_checkbox_is_enabled(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Literasi Latex',
            'username' => 'admin-literasi-latex',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(PerpustakaanLiterasiMaterialResource::getUrl('create'))
            ->assertOk()
            ->assertSee('Tampilkan template rumus LaTeX')
            ->assertSee('Editor mendukung tebal, miring, heading, warna teks, tabel, kolom, dan upload gambar langsung di posisi kursor')
            ->assertSee('Arahan / Tatib Pengerjaan')
            ->assertSee('Jika kosong, halaman publik memakai arahan default anti menyontek.')
            ->assertSee('Aktifkan deteksi plagiasi')
            ->assertSee('Sistem menyimpan satu pembanding terkuat mulai 80%')
            ->assertSee('Jawaban yang sama dengan kunci otomatis Benar dan dikecualikan dari indikasi kemiripan')
            ->assertSee('Kunci Jawaban')
            ->assertDontSee('Template Rumus Cepat')
            ->assertSee('tex-chtml.js', false);
    }

    public function test_admin_edit_form_shows_latex_preview_for_existing_formula_question(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Edit Literasi Latex',
            'username' => 'admin-edit-literasi-latex',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $material = $this->createMaterial('Materi Edit Rumus', [
            'reading_content' => 'Materi menggunakan rumus \(\frac{a}{b}\).',
        ]);
        $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Tentukan nilai \(\sqrt{81}\) dan \(x^{2}\).',
            'max_characters' => 500,
        ]);

        $this->actingAs($admin)
            ->get(PerpustakaanLiterasiMaterialResource::getUrl('edit', ['record' => $material]))
            ->assertOk()
            ->assertSee('Tampilkan template rumus LaTeX')
            ->assertSee('Template Rumus Cepat')
            ->assertSee('Pangkat dan Akar')
            ->assertSee('Pecahan dan Pembagian')
            ->assertSee('Simbol Operasi & Relasi')
            ->assertSee('Matriks (Paling Sering Dicari)')
            ->assertSee('Preview Tampilan')
            ->assertSee('data-literacy-latex-preview', false)
            ->assertSee('data-literacy-latex-template', false)
            ->assertSee('\(\frac{a}{b}\)', false)
            ->assertSee('\(\sqrt{81}\)', false);
    }

    public function test_admin_can_grade_literacy_answers_and_viewer_cannot(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $guru = User::query()->create([
            'name' => 'Guru Penilai Literasi',
            'username' => 'guru-penilai-literasi',
            'password' => bcrypt('password'),
            'module_access_levels' => AdminModuleAccess::normalizeLevels([
                'perpustakaan_literasi' => AdminModuleAccess::MANAGE,
            ]),
        ]);
        $guru->assignRole('guru');

        $viewer = User::query()->create([
            'name' => 'Guru Viewer Literasi',
            'username' => 'guru-viewer-literasi',
            'password' => bcrypt('password'),
            'module_access_levels' => AdminModuleAccess::normalizeLevels([
                'perpustakaan_literasi' => AdminModuleAccess::VIEW,
            ]),
        ]);
        $viewer->assignRole('guru');

        $student = $this->createStudent('Codex Literasi Nilai', 'XI Nilai');
        $material = $this->createMaterial('Materi Penilaian Jawaban');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Apa jawaban yang benar?',
            'answer_key' => 'Jawaban kunci untuk dicek guru.',
            'max_characters' => 500,
        ]);
        $response = $this->createResponseWithAnswer($material, $student, $question, 'Jawaban siswa untuk dinilai oleh guru.');
        $answer = $response->answers()->firstOrFail();
        $previousResponse = $this->createResponseWithAnswer(
            $material,
            $this->createStudent('Codex Pembanding Nilai', 'XI Nilai'),
            $question,
            'Jawaban pembanding yang mirip dengan jawaban siswa untuk ditinjau.'
        );
        $previousAnswer = $previousResponse->answers()->firstOrFail();
        $match = PerpustakaanLiterasiSimilarityMatch::query()->create([
            'material_id' => $material->getKey(),
            'question_id' => $question->getKey(),
            'later_response_id' => $response->getKey(),
            'matched_response_id' => $previousResponse->getKey(),
            'later_answer_id' => $answer->getKey(),
            'matched_answer_id' => $previousAnswer->getKey(),
            'student_class_snapshot' => 'XI Nilai',
            'similarity_score' => 94.25,
            'later_submitted_at' => now(),
            'matched_submitted_at' => now()->subMinute(),
        ]);

        $response->forceFill([
            'tab_switch_count' => 2,
            'app_hidden_count' => 1,
            'page_leave_attempt_count' => 3,
            'submission_delivery_code' => PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_RETRY_503,
            'submission_queue_wait_seconds' => 6,
            'submission_retry_statuses' => ['503'],
        ])->save();

        $detailHtml = view(
            'filament.resources.perpustakaan-literasi-material-resource.partials.response-detail',
            ['response' => $response->load(
                'material.questions',
                'answers.question',
                'answers.gradedBy',
                'laterSimilarityMatches.matchedResponse',
                'laterSimilarityMatches.reviewedBy',
            )]
        )->render();

        $this->assertStringContainsString('literasi-response-detail__identity', $detailHtml);
        $this->assertStringContainsString('Tindakan keluar halaman', $detailHtml);
        $this->assertStringContainsString('Total Indikator', $detailHtml);
        $this->assertStringContainsString('Halaman Disembunyikan', $detailHtml);
        $this->assertStringContainsString('1x', $detailHtml);
        $this->assertStringNotContainsString('Percobaan Keluar', $detailHtml);
        $this->assertStringNotContainsString('Pindah Tab', $detailHtml);
        $this->assertStringContainsString('Terindikasi kemiripan', $detailHtml);
        $this->assertStringContainsString('94,25% mirip', $detailHtml);
        $this->assertStringContainsString('Codex Pembanding Nilai', $detailHtml);
        $this->assertStringContainsString('Belum ditinjau', $detailHtml);

        Livewire::actingAs($viewer)
            ->test(ResponsesRelationManager::class, [
                'ownerRecord' => $material,
                'pageClass' => ViewPerpustakaanLiterasiMaterial::class,
            ])
            ->assertTableActionHidden('nilaiJawaban', $response);

        $gradingComponent = Livewire::actingAs($guru)
            ->test(ResponsesRelationManager::class, [
                'ownerRecord' => $material,
                'pageClass' => ViewPerpustakaanLiterasiMaterial::class,
            ])
            ->assertSee('Status Submit')
            ->assertSee(PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_RETRY_503)
            ->assertSee('Antre 6 detik | Retry 503');

        $schemaMethod = new \ReflectionMethod(ResponsesRelationManager::class, 'gradingFormSchema');
        $schemaMethod->setAccessible(true);
        $gradingSchema = $schemaMethod->invoke($gradingComponent->instance(), $response);
        $childComponents = new \ReflectionProperty($gradingSchema[1], 'childComponents');
        $childComponents->setAccessible(true);
        $integrityComponents = $childComponents->getValue($gradingSchema[0])['default'] ?? [];
        $submittedAtPlaceholder = collect($integrityComponents)
            ->first(fn ($component): bool => method_exists($component, 'getName')
                && $component->getName() === 'submitted_at');
        $submittedAtLabel = new \ReflectionProperty($submittedAtPlaceholder, 'label');
        $submittedAtLabel->setAccessible(true);

        $this->assertNotNull($submittedAtPlaceholder);
        $this->assertSame('Submit jawaban', $submittedAtLabel->getValue($submittedAtPlaceholder));

        $answerKeyPlaceholder = collect($gradingSchema)
            ->flatMap(fn ($section): array => $childComponents->getValue($section)['default'] ?? [])
            ->first(fn ($component): bool => method_exists($component, 'getName')
                && $component->getName() === 'answer_'.$answer->getKey().'_answer_key');

        $this->assertNotNull($answerKeyPlaceholder);
        $answerKeyState = new \ReflectionProperty($answerKeyPlaceholder, 'getConstantStateUsing');
        $answerKeyState->setAccessible(true);
        $answerKeyContent = strip_tags((string) $answerKeyState->getValue($answerKeyPlaceholder));

        $this->assertStringContainsString('Jawaban kunci untuk dicek guru.', $answerKeyContent);

        $historyComponent = Livewire::actingAs($guru)
            ->test(StudentHistoryPerpustakaanLiterasi::class);
        $historySchemaMethod = new \ReflectionMethod(StudentHistoryPerpustakaanLiterasi::class, 'gradingFormSchema');
        $historySchemaMethod->setAccessible(true);
        $historySchema = $historySchemaMethod->invoke($historyComponent->instance(), $response);
        $historyAnswerKey = collect($historySchema)
            ->flatMap(fn ($section): array => $childComponents->getValue($section)['default'] ?? [])
            ->first(fn ($component): bool => method_exists($component, 'getName')
                && $component->getName() === 'answer_'.$answer->getKey().'_answer_key');

        $this->assertNotNull($historyAnswerKey, 'History siswa harus memakai form nilai bersama yang menampilkan kunci jawaban.');

        Livewire::actingAs($guru)
            ->test(ResponsesRelationManager::class, [
                'ownerRecord' => $material,
                'pageClass' => ViewPerpustakaanLiterasiMaterial::class,
            ])
            ->callTableAction('nilaiJawaban', $response, [
                'answer_'.$answer->getKey().'_status' => 'correct',
                'answer_'.$answer->getKey().'_note' => 'Sudah sesuai.',
                'answer_'.$answer->getKey().'_plagiarism_status' => PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED,
            ])
            ->assertHasNoTableActionErrors();

        $answer->refresh();
        $this->assertTrue($answer->is_correct);
        $this->assertSame($guru->getKey(), (int) $answer->graded_by);
        $this->assertNotNull($answer->graded_at);
        $this->assertSame('Sudah sesuai.', $answer->grading_note);
        $this->assertSame($guru->name, $answer->gradedBy?->name);
        $match->refresh();
        $this->assertSame(PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED, $match->review_status);
        $this->assertSame($guru->getKey(), (int) $match->reviewed_by);
        $this->assertNotNull($match->reviewed_at);

        Livewire::actingAs($guru)
            ->test(ResponsesRelationManager::class, [
                'ownerRecord' => $material,
                'pageClass' => ViewPerpustakaanLiterasiMaterial::class,
            ])
            ->callTableAction('nilaiJawaban', $response, [
                'answer_'.$answer->getKey().'_status' => 'ungraded',
                'answer_'.$answer->getKey().'_note' => 'Catatan ini harus dihapus.',
                'answer_'.$answer->getKey().'_plagiarism_status' => PerpustakaanLiterasiSimilarityMatch::REVIEW_CLEARED,
            ])
            ->assertHasNoTableActionErrors();

        $answer->refresh();
        $this->assertNull($answer->is_correct);
        $this->assertNull($answer->graded_by);
        $this->assertNull($answer->graded_at);
        $this->assertNull($answer->grading_note);
        $match->refresh();
        $this->assertSame(PerpustakaanLiterasiSimilarityMatch::REVIEW_CLEARED, $match->review_status);

        Livewire::actingAs($guru)
            ->test(StudentHistoryPerpustakaanLiterasi::class)
            ->callTableAction('detailEdit', $response, [
                'answer_'.$answer->getKey().'_status' => 'wrong',
                'answer_'.$answer->getKey().'_note' => 'Direvisi dari halaman history.',
                'answer_'.$answer->getKey().'_plagiarism_status' => PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED,
            ])
            ->assertHasNoTableActionErrors();

        $answer->refresh();
        $match->refresh();
        $this->assertFalse($answer->is_correct);
        $this->assertSame($guru->getKey(), (int) $answer->graded_by);
        $this->assertSame('Direvisi dari halaman history.', $answer->grading_note);
        $this->assertSame(PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED, $match->review_status);
    }

    public function test_admin_can_bulk_trash_restore_and_force_delete_material_responses(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = User::query()->create([
            'name' => 'Admin Bulk Jawaban Literasi',
            'username' => 'admin-bulk-jawaban-literasi',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $material = $this->createMaterial('Materi Bulk Jawaban');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Pertanyaan bulk jawaban?',
            'max_characters' => 500,
        ]);
        $keptResponse = $this->createResponseWithAnswer(
            $material,
            $this->createStudent('Codex Jawaban Tetap', 'X Bulk'),
            $question,
            'Jawaban pembanding yang tetap aktif.'
        );
        $deletedResponse = $this->createResponseWithAnswer(
            $material,
            $this->createStudent('Codex Jawaban Dihapus', 'X Bulk'),
            $question,
            'Jawaban yang akan masuk sampah.'
        );
        $keptAnswer = $keptResponse->answers()->firstOrFail();
        $deletedAnswer = $deletedResponse->answers()->firstOrFail();
        $match = PerpustakaanLiterasiSimilarityMatch::query()->create([
            'material_id' => $material->getKey(),
            'question_id' => $question->getKey(),
            'later_response_id' => $deletedResponse->getKey(),
            'matched_response_id' => $keptResponse->getKey(),
            'later_answer_id' => $deletedAnswer->getKey(),
            'matched_answer_id' => $keptAnswer->getKey(),
            'student_class_snapshot' => 'X Bulk',
            'similarity_score' => 92.5,
            'later_submitted_at' => now(),
            'matched_submitted_at' => now()->subMinute(),
        ]);

        Livewire::actingAs($admin)
            ->test(ResponsesRelationManager::class, [
                'ownerRecord' => $material,
                'pageClass' => ViewPerpustakaanLiterasiMaterial::class,
            ])
            ->callTableBulkAction('deleteSelectedResponses', [$deletedResponse])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSoftDeleted('perpustakaan_literasi_responses', ['id' => $deletedResponse->getKey()]);
        $this->assertSoftDeleted('perpustakaan_literasi_answers', ['id' => $deletedAnswer->getKey()]);
        $this->assertSoftDeleted('perpustakaan_literasi_similarity_matches', ['id' => $match->getKey()]);
        $this->assertDatabaseHas('perpustakaan_literasi_responses', [
            'id' => $keptResponse->getKey(),
            'deleted_at' => null,
        ]);

        $trashedResponse = PerpustakaanLiterasiResponse::onlyTrashed()->findOrFail($deletedResponse->getKey());

        Livewire::actingAs($admin)
            ->test(ResponsesRelationManager::class, [
                'ownerRecord' => $material,
                'pageClass' => ViewPerpustakaanLiterasiMaterial::class,
            ])
            ->callTableAction('showTrashedResponses')
            ->assertCanSeeTableRecords([$trashedResponse])
            ->assertCanNotSeeTableRecords([$keptResponse]);

        Livewire::actingAs($admin)
            ->test(ResponsesRelationManager::class, [
                'ownerRecord' => $material,
                'pageClass' => ViewPerpustakaanLiterasiMaterial::class,
            ])
            ->filterTable('response_status', 'trash')
            ->callTableBulkAction('restoreSelectedResponses', [$trashedResponse])
            ->assertHasNoTableBulkActionErrors();

        $this->assertFalse(PerpustakaanLiterasiResponse::withTrashed()->findOrFail($deletedResponse->getKey())->trashed());
        $this->assertFalse(PerpustakaanLiterasiAnswer::withTrashed()->findOrFail($deletedAnswer->getKey())->trashed());
        $this->assertFalse(PerpustakaanLiterasiSimilarityMatch::withTrashed()->findOrFail($match->getKey())->trashed());

        $restoredResponse = PerpustakaanLiterasiResponse::query()->findOrFail($deletedResponse->getKey());
        Livewire::actingAs($admin)
            ->test(ResponsesRelationManager::class, [
                'ownerRecord' => $material,
                'pageClass' => ViewPerpustakaanLiterasiMaterial::class,
            ])
            ->callTableBulkAction('deleteSelectedResponses', [$restoredResponse])
            ->assertHasNoTableBulkActionErrors();

        $trashedResponse = PerpustakaanLiterasiResponse::onlyTrashed()->findOrFail($deletedResponse->getKey());
        Livewire::actingAs($admin)
            ->test(ResponsesRelationManager::class, [
                'ownerRecord' => $material,
                'pageClass' => ViewPerpustakaanLiterasiMaterial::class,
            ])
            ->filterTable('response_status', 'trash')
            ->callTableBulkAction('forceDeleteSelectedResponses', [$trashedResponse])
            ->assertHasNoTableBulkActionErrors();

        $this->assertNull(PerpustakaanLiterasiResponse::withTrashed()->find($deletedResponse->getKey()));
        $this->assertNull(PerpustakaanLiterasiAnswer::withTrashed()->find($deletedAnswer->getKey()));
        $this->assertNull(PerpustakaanLiterasiSimilarityMatch::withTrashed()->find($match->getKey()));
        $this->assertNotNull(PerpustakaanLiterasiResponse::withTrashed()->find($keptResponse->getKey()));
        $this->assertNotNull(PerpustakaanLiterasiAnswer::withTrashed()->find($keptAnswer->getKey()));

        $emptyTrashResponse = $this->createResponseWithAnswer(
            $material,
            $this->createStudent('Codex Kosongkan Sampah', 'X Bulk'),
            $question,
            'Jawaban yang akan dihapus lewat menu kosongkan sampah.'
        );
        $emptyTrashAnswer = $emptyTrashResponse->answers()->firstOrFail();
        $emptyTrashResponse->delete();

        Livewire::actingAs($admin)
            ->test(ResponsesRelationManager::class, [
                'ownerRecord' => $material,
                'pageClass' => ViewPerpustakaanLiterasiMaterial::class,
            ])
            ->callTableAction('emptyResponseTrash')
            ->assertHasNoTableActionErrors();

        $this->assertNull(PerpustakaanLiterasiResponse::withTrashed()->find($emptyTrashResponse->getKey()));
        $this->assertNull(PerpustakaanLiterasiAnswer::withTrashed()->find($emptyTrashAnswer->getKey()));
        $this->assertNotNull(PerpustakaanLiterasiResponse::query()->find($keptResponse->getKey()));
    }

    public function test_admin_can_bulk_grade_selected_responses_and_questions(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = User::query()->create([
            'name' => 'Admin Bulk Nilai Literasi',
            'username' => 'admin-bulk-nilai-literasi',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $material = $this->createMaterial('Materi Bulk Nilai');
        $questionOne = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Pertanyaan pertama untuk bulk nilai?',
            'max_characters' => 500,
        ]);
        $questionTwo = $material->questions()->create([
            'sort_order' => 2,
            'prompt' => 'Pertanyaan kedua untuk bulk nilai?',
            'max_characters' => 500,
        ]);
        $responseOne = $this->createResponseWithAnswer(
            $material,
            $this->createStudent('Codex Bulk Nilai Satu', 'XI Nilai'),
            $questionOne,
            'Jawaban siswa pertama untuk pertanyaan pertama.'
        );
        $responseTwo = $this->createResponseWithAnswer(
            $material,
            $this->createStudent('Codex Bulk Nilai Dua', 'XI Nilai'),
            $questionOne,
            'Jawaban siswa kedua untuk pertanyaan pertama.'
        );

        foreach ([$responseOne, $responseTwo] as $response) {
            PerpustakaanLiterasiAnswer::query()->create([
                'response_id' => $response->getKey(),
                'question_id' => $questionTwo->getKey(),
                'answer_text' => 'Jawaban untuk pertanyaan kedua yang belum ikut dinilai.',
                'character_count' => 54,
            ]);
        }

        Livewire::actingAs($admin)
            ->test(ResponsesRelationManager::class, [
                'ownerRecord' => $material,
                'pageClass' => ViewPerpustakaanLiterasiMaterial::class,
            ])
            ->mountTableBulkAction('gradeSelectedResponses', [$responseOne, $responseTwo])
            ->setTableBulkActionData([
                'question_ids' => [$questionOne->getKey()],
                'status' => 'correct',
                'note' => 'Dinilai benar secara bulk.',
            ])
            ->assertTableBulkActionDataSet([
                'question_ids' => [$questionOne->getKey()],
                'status' => 'correct',
                'note' => 'Dinilai benar secara bulk.',
            ])
            ->callMountedTableBulkAction()
            ->assertHasNoTableBulkActionErrors();

        $gradedAnswers = PerpustakaanLiterasiAnswer::query()
            ->whereIn('response_id', [$responseOne->getKey(), $responseTwo->getKey()])
            ->where('question_id', $questionOne->getKey())
            ->get();

        $this->assertCount(2, $gradedAnswers);
        $this->assertTrue($gradedAnswers->every(fn (PerpustakaanLiterasiAnswer $answer): bool => $answer->is_correct === true));
        $this->assertTrue($gradedAnswers->every(fn (PerpustakaanLiterasiAnswer $answer): bool => (int) $answer->graded_by === (int) $admin->getKey()));
        $this->assertTrue($gradedAnswers->every(fn (PerpustakaanLiterasiAnswer $answer): bool => $answer->grading_note === 'Dinilai benar secara bulk.'));

        $this->assertSame(0, PerpustakaanLiterasiAnswer::query()
            ->whereIn('response_id', [$responseOne->getKey(), $responseTwo->getKey()])
            ->where('question_id', $questionTwo->getKey())
            ->whereNotNull('is_correct')
            ->count());
    }

    public function test_public_edit_clears_existing_literacy_grading_when_answer_changes(): void
    {
        $grader = User::query()->create([
            'name' => 'Guru Reset Nilai',
            'username' => 'guru-reset-nilai-literasi',
            'password' => bcrypt('password'),
        ]);
        $student = $this->createStudent('Codex Literasi Edit Nilai', 'X Edit Nilai');
        $material = $this->createMaterial('Materi Edit Reset Nilai');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jawaban awal.',
            'min_characters' => 10,
            'max_characters' => 500,
        ]);
        $response = $this->createResponseWithAnswer($material, $student, $question, 'Jawaban lama yang sudah dinilai benar.');
        $answer = $response->answers()->firstOrFail();
        $answer->forceFill([
            'is_correct' => true,
            'graded_by' => $grader->getKey(),
            'graded_at' => now(),
            'grading_note' => 'Nilai lama.',
        ])->save();

        $this->post(route('library.literacy.update', $response->shortEditCode()), [
            'answers' => [
                $question->getKey() => 'Jawaban baru siswa setelah melakukan revisi materi.',
            ],
        ])->assertRedirect(route('library.literacy.completed'));

        $answer->refresh();
        $this->assertSame('Jawaban baru siswa setelah melakukan revisi materi.', $answer->answer_text);
        $this->assertNull($answer->is_correct);
        $this->assertNull($answer->graded_by);
        $this->assertNull($answer->graded_at);
        $this->assertNull($answer->grading_note);
    }

    public function test_literacy_material_analytics_ranks_classes_students_and_later_plagiarism(): void
    {
        $studentA = $this->createStudent('Codex Ranking A', 'X Ranking');
        $studentB = $this->createStudent('Codex Ranking B', 'X Ranking');
        $studentC = $this->createStudent('Codex Ranking C', 'XI Ranking');
        $this->createStudent('Codex Ranking Belum Mengisi', 'X Kosong');
        $material = $this->createMaterial('Materi Ranking Bulanan');
        $questionOne = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Soal satu.',
            'max_characters' => 500,
        ]);
        $questionTwo = $material->questions()->create([
            'sort_order' => 2,
            'prompt' => 'Soal dua.',
            'max_characters' => 500,
        ]);

        $responseA = $this->createResponseWithAnswer($material, $studentA, $questionOne, 'Jawaban A satu.', now());
        $answerA1 = $responseA->answers()->firstOrFail();
        $answerA2 = PerpustakaanLiterasiAnswer::query()->create([
            'response_id' => $responseA->getKey(),
            'question_id' => $questionTwo->getKey(),
            'answer_text' => 'Jawaban A dua.',
            'character_count' => 14,
            'is_correct' => false,
            'graded_at' => now(),
        ]);
        $answerA1->forceFill(['is_correct' => true, 'graded_at' => now()])->save();

        $responseB = $this->createResponseWithAnswer($material, $studentB, $questionOne, 'Jawaban B satu.', now()->addMinute());
        $answerB1 = $responseB->answers()->firstOrFail();
        $answerB1->forceFill(['is_correct' => true, 'graded_at' => now()])->save();

        $responseC = $this->createResponseWithAnswer($material, $studentC, $questionOne, 'Jawaban C satu.', now()->addMinutes(2));
        $answerC1 = $responseC->answers()->firstOrFail();
        $answerC1->forceFill(['is_correct' => false, 'graded_at' => now()])->save();

        $previousMonth = now()->subMonthNoOverflow()->startOfMonth();
        $oldResponse = $this->createResponseWithAnswer($material, $this->createStudent('Codex Ranking Old', 'X Ranking'), $questionOne, 'Jawaban bulan lalu.', $previousMonth);
        $oldResponse->answers()->firstOrFail()->forceFill(['is_correct' => true, 'graded_at' => $previousMonth])->save();

        PerpustakaanLiterasiSimilarityMatch::query()->create([
            'material_id' => $material->getKey(),
            'question_id' => $questionOne->getKey(),
            'later_response_id' => $responseB->getKey(),
            'matched_response_id' => $responseA->getKey(),
            'later_answer_id' => $answerB1->getKey(),
            'matched_answer_id' => $answerA1->getKey(),
            'student_class_snapshot' => 'X Ranking',
            'similarity_score' => 95.5,
            'later_submitted_at' => now(),
            'matched_submitted_at' => now()->subMinute(),
        ]);
        PerpustakaanLiterasiSimilarityMatch::query()->create([
            'material_id' => $material->getKey(),
            'question_id' => $questionTwo->getKey(),
            'later_response_id' => $responseC->getKey(),
            'matched_response_id' => $responseA->getKey(),
            'later_answer_id' => $answerC1->getKey(),
            'matched_answer_id' => $answerA2->getKey(),
            'student_class_snapshot' => 'XI Ranking',
            'similarity_score' => 91.0,
            'later_submitted_at' => now(),
            'matched_submitted_at' => now()->subMinute(),
        ]);

        $analytics = LiterasiAnalytics::forMaterial($material);
        $classActivity = collect($analytics['class_activity'])->keyBy('class');
        $classRanking = collect($analytics['class_response_ranking'])->keyBy('class');
        $classCorrectRanking = collect($analytics['class_correct_ranking'])->keyBy('class');
        $leastClassRanking = collect($analytics['least_class_response_ranking']);
        $studentRanking = collect($analytics['student_correct_ranking_by_class']['X Ranking'] ?? [])->keyBy('name');
        $wrongStudents = collect($analytics['student_wrong_ranking'])->keyBy('name');
        $missingStudents = collect($analytics['missing_students'])->keyBy('name');
        $plagiarismStudents = collect($analytics['plagiarism_student_ranking'])->keyBy('name');

        $this->assertSame(2, (int) $classActivity['X Ranking']['month']);
        $this->assertSame(1, (int) $classActivity['XI Ranking']['month']);
        $this->assertSame(2, (int) $classRanking['X Ranking']['total']);
        $this->assertSame(2, (int) $classCorrectRanking['X Ranking']['correct_answers']);
        $this->assertSame(2, (int) $classCorrectRanking['X Ranking']['response_count']);
        $this->assertFalse($classCorrectRanking->has('XI Ranking'));
        $this->assertSame('X Kosong', $leastClassRanking->first()['class']);
        $this->assertSame(0, (int) $leastClassRanking->first()['total']);
        $this->assertSame(1, (int) $studentRanking['Codex Ranking B']['correct_answers']);
        $this->assertSame(100.0, (float) $studentRanking['Codex Ranking B']['accuracy']);
        $this->assertSame(1, (int) $wrongStudents['Codex Ranking A']['wrong_answers']);
        $this->assertSame('X Kosong', $missingStudents['Codex Ranking Belum Mengisi']['class']);
        $this->assertSame(1, (int) $plagiarismStudents['Codex Ranking B']['total']);
        $this->assertSame(1, (int) $plagiarismStudents['Codex Ranking C']['total']);
        $this->assertFalse($plagiarismStudents->has('Codex Ranking A'));
        $this->assertSame(3, (int) $analytics['grading_summary']['responses']);
        $this->assertSame(2, (int) $analytics['grading_summary']['correct_answers']);
    }

    public function test_monthly_whatsapp_share_uses_response_statuses_categories_and_unique_similarity_students(): void
    {
        $literacy = $this->createMaterial('Materi Rekap Literasi');
        $questionOne = $literacy->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Pertanyaan pertama rekap bulanan.',
            'max_characters' => 500,
        ]);
        $questionTwo = $literacy->questions()->create([
            'sort_order' => 2,
            'prompt' => 'Pertanyaan kedua rekap bulanan.',
            'max_characters' => 500,
        ]);

        $fullyGradedStudent = $this->createStudent('Alya Rekap Lengkap', 'X 1');
        $partiallyGradedStudent = $this->createStudent('Bima Rekap Sebagian', 'X 1');
        $suspectedStudent = $this->createStudent('Citra Rekap Indikasi', 'X 2');
        $clearedStudent = $this->createStudent('Dina Rekap Aman', 'X 2');
        $dispensatedStudent = $this->createStudent('Eka Rekap Sakit', 'XI 1');
        $this->createStudent('Fina Belum Mengisi', 'XI 2');

        $fullyGraded = $this->createResponseWithAnswer($literacy, $fullyGradedStudent, $questionOne, 'Jawaban Alya pertama.', now()->subMinutes(4));
        $fullyGradedAnswerOne = $fullyGraded->answers()->firstOrFail();
        $fullyGradedAnswerOne->forceFill(['is_correct' => true, 'graded_at' => now()])->save();
        $fullyGradedAnswerTwo = PerpustakaanLiterasiAnswer::query()->create([
            'response_id' => $fullyGraded->getKey(),
            'question_id' => $questionTwo->getKey(),
            'answer_text' => 'Jawaban Alya kedua.',
            'character_count' => 20,
            'is_correct' => true,
            'graded_at' => now(),
        ]);

        $partiallyGraded = $this->createResponseWithAnswer($literacy, $partiallyGradedStudent, $questionOne, 'Jawaban Bima pertama.', now()->subMinutes(3));
        $partiallyGradedAnswerOne = $partiallyGraded->answers()->firstOrFail();
        $partiallyGradedAnswerOne->forceFill(['is_correct' => true, 'graded_at' => now()])->save();
        $partiallyGradedAnswerTwo = PerpustakaanLiterasiAnswer::query()->create([
            'response_id' => $partiallyGraded->getKey(),
            'question_id' => $questionTwo->getKey(),
            'answer_text' => 'Jawaban Bima kedua belum dinilai.',
            'character_count' => 33,
        ]);

        $suspected = $this->createResponseWithAnswer($literacy, $suspectedStudent, $questionOne, 'Jawaban Citra terindikasi.', now()->subMinutes(2));
        $suspectedAnswer = $suspected->answers()->firstOrFail();
        $suspectedAnswer->forceFill(['is_correct' => false, 'graded_at' => now()])->save();
        $cleared = $this->createResponseWithAnswer($literacy, $clearedStudent, $questionOne, 'Jawaban Dina sudah aman.', now()->subMinute());
        $clearedAnswer = $cleared->answers()->firstOrFail();
        $clearedAnswer->forceFill(['is_correct' => true, 'graded_at' => now()])->save();

        PerpustakaanLiterasiDispensation::query()->create([
            'material_id' => $literacy->getKey(),
            'data_siswa_id' => $dispensatedStudent->getKey(),
            'reason' => PerpustakaanLiterasiDispensation::REASON_SICK,
            'student_name_snapshot' => $dispensatedStudent->nama,
            'student_class_snapshot' => $dispensatedStudent->rombel_saat_ini,
            'confirmed_at' => now(),
        ]);

        foreach ([
            [$partiallyGraded, $partiallyGradedAnswerOne, PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED, 91.0],
            [$partiallyGraded, $partiallyGradedAnswerTwo, PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED, 96.0],
            [$suspected, $suspectedAnswer, PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED, 88.0],
            [$cleared, $clearedAnswer, PerpustakaanLiterasiSimilarityMatch::REVIEW_CLEARED, 85.0],
        ] as [$laterResponse, $laterAnswer, $status, $score]) {
            PerpustakaanLiterasiSimilarityMatch::query()->create([
                'material_id' => $literacy->getKey(),
                'question_id' => $laterAnswer->question_id,
                'later_response_id' => $laterResponse->getKey(),
                'matched_response_id' => $fullyGraded->getKey(),
                'later_answer_id' => $laterAnswer->getKey(),
                'matched_answer_id' => $laterAnswer->question_id === $questionTwo->getKey()
                    ? $fullyGradedAnswerTwo->getKey()
                    : $fullyGradedAnswerOne->getKey(),
                'student_class_snapshot' => $laterResponse->student_class_snapshot,
                'similarity_score' => $score,
                'review_status' => $status,
                'later_submitted_at' => $laterResponse->submitted_at,
                'matched_submitted_at' => $fullyGraded->submitted_at,
            ]);
        }

        $legacy = $this->createMaterial('Materi Lama Tanpa Kategori');
        DB::table('perpustakaan_literasi_materials')->where('id', $legacy->getKey())->update(['program_category' => null]);
        $legacyQuestion = $legacy->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Pertanyaan materi lama.',
            'max_characters' => 500,
        ]);
        $legacyStudent = $this->createStudent('Gina Materi Lama', 'XII 1');
        $legacyResponse = $this->createResponseWithAnswer($legacy, $legacyStudent, $legacyQuestion, 'Jawaban materi lama.');
        $legacyResponse->answers()->firstOrFail()->forceFill(['is_correct' => true, 'graded_at' => now()])->save();

        $literacyAnalytics = LiterasiAnalytics::monthlyShare(PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION);
        $allAnalytics = LiterasiAnalytics::monthlyShare();
        $summary = $literacyAnalytics['grading_summary'];

        $this->assertSame(5, $summary['responses']);
        $this->assertSame(4, $summary['response_records']);
        $this->assertSame(1, $summary['dispensations']);
        $this->assertSame(5, $summary['unique_students']);
        $this->assertSame(3, $summary['fully_graded_responses']);
        $this->assertSame(1, $summary['pending_grading_responses']);
        $this->assertSame(1, $summary['confirmed_plagiarism_students']);
        $this->assertSame(1, $summary['pending_similarity_students']);
        $this->assertSame(5, $allAnalytics['grading_summary']['response_records']);
        $this->assertSame('XI 2', collect($literacyAnalytics['class_participation'])->firstWhere('total', 0)['class']);

        $activeSimilarityStudents = collect($literacyAnalytics['plagiarism_student_ranking'])->pluck('name');
        $this->assertTrue($activeSimilarityStudents->contains('Bima Rekap Sebagian'));
        $this->assertTrue($activeSimilarityStudents->contains('Citra Rekap Indikasi'));
        $this->assertFalse($activeSimilarityStudents->contains('Dina Rekap Aman'));

        $text = LiteracyMonthlyShareText::make(
            PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION,
            CarbonImmutable::create(2026, 8, 6, 12, 0, 0, 'Asia/Jakarta'),
        );
        $this->assertStringContainsString('*Lingkup:* Literasi', $text);
        $this->assertStringContainsString('*Dibuat:* 06/08/2026 12:00 WIB', $text);
        $this->assertStringContainsString('- Total responden: 5 partisipasi dari 5 siswa unik', $text);
        $this->assertStringContainsString('- Sudah dinilai lengkap: 3 respons', $text);
        $this->assertStringContainsString('- Belum dinilai/masih sebagian: 1 respons', $text);
        $this->assertStringContainsString('*Kelas XI 2*', $text);
        $this->assertStringContainsString('Fina Belum Mengisi', $text);
        $this->assertStringContainsString('Bima Rekap Sebagian', $text);
        $this->assertStringNotContainsString('Dina Rekap Aman — X 2 — 1 indikasi', $text);
    }

    public function test_monthly_whatsapp_share_does_not_truncate_student_lists(): void
    {
        $material = $this->createMaterial('Materi Rekap Tanpa Batas');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Pertanyaan rekap tanpa batas.',
            'max_characters' => 500,
        ]);

        for ($index = 1; $index <= 6; $index++) {
            $student = $this->createStudent(sprintf('Siswa Dinilai %02d', $index), 'X Rekap');
            $response = $this->createResponseWithAnswer($material, $student, $question, 'Jawaban siswa '.$index.'.');
            $response->answers()->firstOrFail()->forceFill(['is_correct' => true, 'graded_at' => now()])->save();
        }

        for ($index = 1; $index <= 31; $index++) {
            $this->createStudent(sprintf('Siswa Belum %02d', $index), 'XI Rekap');
        }

        $text = LiteracyMonthlyShareText::make(PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION);

        $this->assertStringContainsString('Siswa Dinilai 06', $text);
        $this->assertStringContainsString('Siswa Belum 31', $text);
    }

    public function test_monthly_whatsapp_share_isolates_all_four_scopes_and_total_includes_legacy_materials(): void
    {
        $scopes = [
            PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION => 'Siswa Scope Literasi',
            PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE => 'Siswa Scope Numerasi',
            PerpustakaanLiterasiMaterial::CATEGORY_SIGAP_29_KARAKTER => 'Siswa Scope SIGAP',
            '__legacy' => 'Siswa Scope Lama',
        ];

        foreach ($scopes as $category => $studentName) {
            $material = $this->createMaterial('Materi '.$studentName, $category === '__legacy' ? [] : [
                'program_category' => $category,
            ]);

            if ($category === '__legacy') {
                DB::table('perpustakaan_literasi_materials')
                    ->where('id', $material->getKey())
                    ->update(['program_category' => null]);
            }

            $question = $material->questions()->create([
                'sort_order' => 1,
                'prompt' => 'Pertanyaan '.$studentName,
                'max_characters' => 500,
            ]);
            $response = $this->createResponseWithAnswer(
                $material,
                $this->createStudent($studentName, 'X Scope'),
                $question,
                'Jawaban '.$studentName,
            );
            $response->answers()->firstOrFail()->forceFill(['is_correct' => true, 'graded_at' => now()])->save();
        }

        $this->assertSame(4, LiterasiAnalytics::monthlyShare()['grading_summary']['response_records']);
        $this->assertSame(1, LiterasiAnalytics::monthlyShare(
            PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION,
        )['grading_summary']['response_records']);
        $this->assertSame(1, LiterasiAnalytics::monthlyShare(
            PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE,
        )['grading_summary']['response_records']);
        $this->assertSame(1, LiterasiAnalytics::monthlyShare(
            PerpustakaanLiterasiMaterial::CATEGORY_SIGAP_29_KARAKTER,
        )['grading_summary']['response_records']);

        $this->assertStringContainsString('*Lingkup:* Keseluruhan', LiteracyMonthlyShareText::make('all'));
        $this->assertStringContainsString('*Lingkup:* Literasi', LiteracyMonthlyShareText::make(
            PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION,
        ));
        $this->assertStringContainsString('*Lingkup:* Numerasi', LiteracyMonthlyShareText::make(
            PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE,
        ));
        $this->assertStringContainsString('*Lingkup:* SIGAP 29 Karakter', LiteracyMonthlyShareText::make(
            PerpustakaanLiterasiMaterial::CATEGORY_SIGAP_29_KARAKTER,
        ));
    }

    public function test_material_completion_separates_missing_students_from_trashed_responses(): void
    {
        $completedStudent = $this->createStudent('Codex Sudah Mengisi', 'X Status');
        $trashedStudent = $this->createStudent('Codex Jawaban Sampah', 'X Status');
        $missingStudent = $this->createStudent('Codex Belum Mengisi', 'XI Status');
        $material = $this->createMaterial('Materi Status Pengisian');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Tuliskan jawaban status.',
            'max_characters' => 500,
        ]);

        $this->createResponseWithAnswer($material, $completedStudent, $question, 'Jawaban siswa yang sudah mengisi.');
        $trashedResponse = $this->createResponseWithAnswer($material, $trashedStudent, $question, 'Jawaban yang kemudian masuk sampah.');
        $trashedResponse->delete();

        $completion = LiterasiAnalytics::materialCompletion($material);
        $classes = collect($completion['classes'])->keyBy('class');

        $this->assertSame(3, $completion['active_total']);
        $this->assertSame(1, $completion['completed_total']);
        $this->assertSame(1, $completion['missing_total']);
        $this->assertSame(1, $completion['trashed_total']);
        $this->assertSame('Codex Belum Mengisi', $classes['XI Status']['missing_students'][0]['name']);
        $this->assertSame('Codex Jawaban Sampah', $classes['X Status']['trashed_students'][0]['name']);

        $html = (string) PerpustakaanLiterasiMaterialResource::monthlyRankingAnalysisHtml($material);
        $this->assertStringContainsString('Status Pengisian Materi', $html);
        $this->assertStringContainsString($missingStudent->nama, $html);
        $this->assertStringContainsString($trashedStudent->nama, $html);
        $this->assertStringContainsString('Jawaban di Sampah', $html);
    }

    public function test_material_completion_share_text_groups_missing_and_dispensated_students_by_class(): void
    {
        $material = $this->createMaterial('Materi Siap Dibagikan');
        $text = LiteracyCompletionShareText::make($material, [
            'classes' => [
                [
                    'class' => 'XI 2',
                    'missing_students' => [
                        ['name' => 'Zahra Belum Mengisi'],
                    ],
                    'dispensated_students' => [
                        ['name' => 'Yusuf Tes MT', 'reason' => PerpustakaanLiterasiDispensation::REASON_MT_TEST],
                    ],
                ],
                [
                    'class' => 'X 1',
                    'missing_students' => [
                        ['name' => 'Budi Belum Mengisi'],
                    ],
                    'dispensated_students' => [
                        ['name' => 'Aisyah Sakit', 'reason' => PerpustakaanLiterasiDispensation::REASON_SICK],
                        ['name' => 'Rahma Izin', 'reason' => PerpustakaanLiterasiDispensation::REASON_PERMISSION, 'note' => 'Mengikuti kegiatan keluarga.'],
                    ],
                ],
                [
                    'class' => 'X Selesai',
                    'missing_students' => [],
                    'dispensated_students' => [],
                ],
            ],
        ], CarbonImmutable::create(2026, 7, 30, 8, 15, 0, 'Asia/Jakarta'));

        $this->assertSame(1, substr_count($text, '*Kelas X 1*'));
        $this->assertSame(1, substr_count($text, '*Kelas XI 2*'));
        $this->assertStringNotContainsString('X Selesai', $text);
        $this->assertStringContainsString('*Materi:* Materi Siap Dibagikan', $text);
        $this->assertStringContainsString('*Diperbarui:* 30/07/2026 08:15 WIB', $text);
        $this->assertStringContainsString('*Ringkasan:* 2 belum mengisi | 3 dispensasi', $text);
        $this->assertStringContainsString('*Kode:* [SAKIT] 1 siswa | [TES MT] 1 siswa | [IZIN] 1 siswa', $text);
        $this->assertStringContainsString("1. Aisyah Sakit [SAKIT]\n2. Budi Belum Mengisi\n3. Rahma Izin [IZIN: Mengikuti kegiatan keluarga.]", $text);
        $this->assertStringContainsString("1. Yusuf Tes MT [TES MT]\n2. Zahra Belum Mengisi", $text);
        $this->assertStringContainsString('Mohon siswa tanpa kode dispensasi segera mengisi materi.', $text);
    }

    public function test_permission_dispensation_requires_note_and_hides_note_from_student_receipt(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Izin Literasi',
            'username' => 'admin-izin-literasi',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');
        $student = $this->createStudent('Codex Izin Literasi', 'XI Izin');
        $material = $this->createMaterial('Materi Izin Literasi');
        $route = route('admin.perpustakaan-literasi.dispensations.store', [$material, $student]);

        $this->actingAs($admin)
            ->post($route, ['reason' => PerpustakaanLiterasiDispensation::REASON_PERMISSION])
            ->assertSessionHasErrors('note');
        $this->actingAs($admin)
            ->post($route, [
                'reason' => PerpustakaanLiterasiDispensation::REASON_PERMISSION,
                'note' => 'abc',
            ])
            ->assertSessionHasErrors('note');
        $this->actingAs($admin)
            ->post($route, [
                'reason' => PerpustakaanLiterasiDispensation::REASON_PERMISSION,
                'note' => 'Mengikuti kegiatan keluarga yang dikonfirmasi wali kelas.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('perpustakaan_literasi_dispensations', [
            'material_id' => $material->getKey(),
            'data_siswa_id' => $student->getKey(),
            'reason' => 'permission',
            'note' => 'Mengikuti kegiatan keluarga yang dikonfirmasi wali kelas.',
            'confirmed_by' => $admin->getKey(),
        ]);
        $completion = LiterasiAnalytics::materialCompletion($material);
        $dispensated = collect($completion['classes'])->firstWhere('class', 'XI Izin')['dispensated_students'][0];
        $this->assertSame('Izin', $dispensated['reason_label']);
        $this->assertSame($admin->name, $dispensated['confirmed_by']);

        $receiptStatus = app(LiteracyReceiptClassStatus::class)->forReceipt([
            'material_id' => $material->getKey(),
            'student_class' => 'XI Izin',
            'student_id' => 0,
        ]);
        $this->assertSame('Izin', $receiptStatus['dispensated_students'][0]['reason_label']);
        $this->assertArrayNotHasKey('note', $receiptStatus['dispensated_students'][0]);
        $this->assertDatabaseCount('perpustakaan_literasi_responses', 0);
    }

    public function test_admin_can_manage_literacy_dispensations_and_real_submit_revokes_them(): void
    {
        Queue::fake();

        $admin = User::query()->create([
            'name' => 'Admin Dispensasi Literasi',
            'username' => 'admin-dispensasi-literasi',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');
        $viewer = User::query()->create([
            'name' => 'Viewer Dispensasi Literasi',
            'username' => 'viewer-dispensasi-literasi',
            'password' => bcrypt('password'),
        ]);
        $sickStudent = $this->createStudent('Codex Sakit Literasi', 'X Dispensasi');
        $cancelStudent = $this->createStudent('Codex Tes MT Literasi', 'X Dispensasi');
        $trashedStudent = $this->createStudent('Codex Sampah Dispensasi', 'XI Dispensasi');
        $material = $this->createMaterial('Materi Dispensasi', [
            'student_verification_enabled' => false,
        ]);
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jelaskan pemahaman materi dispensasi.',
            'min_characters' => 10,
            'max_characters' => 500,
        ]);
        $trashedResponse = $this->createResponseWithAnswer(
            $material,
            $trashedStudent,
            $question,
            'Jawaban yang berada di Sampah.',
        );
        $trashedResponse->delete();

        $storeRoute = fn (DataSiswa $student): string => route(
            'admin.perpustakaan-literasi.dispensations.store',
            [$material, $student],
        );

        $this->actingAs($viewer)
            ->post($storeRoute($sickStudent), ['reason' => PerpustakaanLiterasiDispensation::REASON_SICK])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post($storeRoute($sickStudent), [
                'reason' => PerpustakaanLiterasiDispensation::REASON_SICK,
                'note' => 'Konfirmasi wali kelas.',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post($storeRoute($sickStudent), [
                'reason' => PerpustakaanLiterasiDispensation::REASON_MT_TEST,
            ])
            ->assertRedirect();

        $this->assertSame(1, PerpustakaanLiterasiDispensation::query()
            ->where('material_id', $material->getKey())
            ->where('data_siswa_id', $sickStudent->getKey())
            ->count());
        $this->assertDatabaseHas('perpustakaan_literasi_dispensations', [
            'material_id' => $material->getKey(),
            'data_siswa_id' => $sickStudent->getKey(),
            'reason' => PerpustakaanLiterasiDispensation::REASON_MT_TEST,
            'confirmed_by' => $admin->getKey(),
            'deleted_at' => null,
        ]);

        $this->actingAs($admin)
            ->post($storeRoute($cancelStudent), [
                'reason' => PerpustakaanLiterasiDispensation::REASON_SICK,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post($storeRoute($trashedStudent), [
                'reason' => PerpustakaanLiterasiDispensation::REASON_SICK,
            ])
            ->assertSessionHasErrors('student');

        $analytics = LiterasiAnalytics::forMaterial($material);
        $completion = $analytics['material_completion'];

        $this->assertSame(2, $completion['dispensation_total']);
        $this->assertSame(2, $completion['respondent_total']);
        $this->assertSame(1, $completion['trashed_total']);
        $this->assertSame(2, $analytics['grading_summary']['responses']);
        $this->assertSame(0, $analytics['grading_summary']['response_records']);
        $this->assertSame(2, $analytics['grading_summary']['dispensations']);
        $this->assertSame(0, $analytics['grading_summary']['total_answers']);
        $this->assertSame(0, $analytics['grading_summary']['correct_answers']);
        $this->assertSame(2, collect($analytics['class_activity'])->firstWhere('class', 'X Dispensasi')['month']);
        $this->assertSame(2, collect($analytics['class_response_ranking'])->firstWhere('class', 'X Dispensasi')['total']);

        $html = (string) PerpustakaanLiterasiMaterialResource::monthlyRankingAnalysisHtml($material);
        $this->assertStringContainsString('Dispensasi', $html);
        $this->assertStringContainsString('Sakit', $html);
        $this->assertStringContainsString('Tes MT', $html);
        $this->assertStringContainsString('Batalkan', $html);
        $this->assertStringContainsString('jawaban +', $html);
        $this->assertStringContainsString('Salin daftar untuk WhatsApp', $html);
        $this->assertStringContainsString('js-literacy-completion-copy', $html);
        $this->assertStringContainsString('[SAKIT]', $html);
        $this->assertStringContainsString('[TES MT]', $html);

        $this->actingAs($admin)
            ->delete(route('admin.perpustakaan-literasi.dispensations.destroy', [$material, $cancelStudent]))
            ->assertRedirect();
        $this->assertSoftDeleted('perpustakaan_literasi_dispensations', [
            'material_id' => $material->getKey(),
            'data_siswa_id' => $cancelStudent->getKey(),
        ]);

        auth()->logout();
        $this->post(route('library.literacy.store', $material->slug), [
            'student_id' => $sickStudent->getKey(),
            'answers' => [
                $question->getKey() => 'Jawaban nyata siswa otomatis membatalkan dispensasi sebelumnya.',
            ],
        ])->assertRedirect(route('library.literacy.completed'));

        $this->assertSoftDeleted('perpustakaan_literasi_dispensations', [
            'material_id' => $material->getKey(),
            'data_siswa_id' => $sickStudent->getKey(),
        ]);
        $this->assertDatabaseHas('perpustakaan_literasi_responses', [
            'material_id' => $material->getKey(),
            'data_siswa_id' => $sickStudent->getKey(),
            'deleted_at' => null,
        ]);
    }

    public function test_literacy_admin_pages_show_monthly_analytics_panels(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Analisa Literasi',
            'username' => 'admin-analisa-literasi',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $material = $this->createMaterial('Materi Panel Analisa', [
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addDays(2)->setTime(16, 45),
        ]);
        $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Judul soal panel analisa literasi',
            'answer_key' => 'Kunci jawaban panel analisa literasi',
            'max_characters' => 500,
        ]);

        $this->actingAs($admin)
            ->get(PerpustakaanLiterasiMaterialResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Ringkasan Literasi')
            ->assertSee('Daftar Materi')
            ->assertSee('Durasi Soal')
            ->assertSee('Status Materi')
            ->assertSee('Tidak Aktif')
            ->assertSee('literasi-status-button--active', false)
            ->assertSee('literasi-status-button--inactive', false)
            ->assertSee('Literacy Habituation Programme')
            ->assertSee('Numeracy Excellence Programme')
            ->assertSee('Sigap 29 Karakter')
            ->assertDontSee('Semua Aktif')
            ->assertDontSee('Soal Non Aktif')
            ->assertSee('Keseluruhan Soal Selama 1 Bulan')
            ->assertSee('Kategori analisa literasi', false)
            ->assertSee('Belum Berkategori')
            ->assertSee('History Pengerjaan Siswa')
            ->assertSee('Salin Rekap Bulanan ke WhatsApp')
            ->assertSee('Rekap Bulanan Total')
            ->assertSee('Rekap SIGAP 29 Karakter')
            ->assertSee('Rekap Literasi')
            ->assertSee('Rekap Numerasi')
            ->assertSee('Hitung Ulang Plagiasi')
            ->assertSee('Setting Tatib')
            ->assertSee('Pilih Kategori')
            ->assertSee('Ranking Kelas Terbanyak Mengisi')
            ->assertSee('Ranking 3 Kelas Jawaban Benar Terbanyak')
            ->assertSee('Ranking 3 Kelas Tersedikit Mengisi')
            ->assertSee('Ranking Siswa Per Kelas Berdasarkan Jawaban Benar')
            ->assertSee('Siswa Banyak Salah')
            ->assertSee('Siswa Tidak Mengisi');

        $materialList = Livewire::actingAs($admin)
            ->test(ListPerpustakaanLiterasiMaterials::class);

        $this->assertSame([
            PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION,
            PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE,
            PerpustakaanLiterasiMaterial::CATEGORY_SIGAP_29_KARAKTER,
        ], array_keys($materialList->instance()->getTabs()));

        $analyticsWidget = Livewire::actingAs($admin)
            ->test(PerpustakaanLiterasiGlobalAnalytics::class)
            ->assertSet('activeAnalyticsTab', 'all')
            ->call('selectAnalyticsTab', PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE)
            ->assertSet('activeAnalyticsTab', PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE)
            ->assertSee('Numeracy Excellence Selama 1 Bulan');

        $analyticsWidget
            ->call('prepareMonthlyShare', LiteracyMonthlyShareText::SCOPE_ALL)
            ->assertSet('monthlyShareTitle', 'Rekap Bulanan Keseluruhan')
            ->assertDispatched('open-modal', id: 'literacy-monthly-share-preview');

        $this->assertStringContainsString(
            '*REKAP BULANAN LITERASI NUMERASI*',
            (string) $analyticsWidget->get('monthlyShareText'),
        );

        Livewire::actingAs($admin)
            ->test(PerpustakaanLiterasiGlobalAnalytics::class)
            ->call('prepareMonthlyShare', 'scope-tidak-valid')
            ->assertHasErrors(['monthlyShareScope']);

        $unauthorized = User::query()->create([
            'name' => 'Pengguna Tanpa Akses Rekap Literasi',
            'username' => 'tanpa-akses-rekap-literasi',
            'password' => bcrypt('password'),
        ]);
        Livewire::actingAs($unauthorized)
            ->test(PerpustakaanLiterasiGlobalAnalytics::class)
            ->call('prepareMonthlyShare', LiteracyMonthlyShareText::SCOPE_ALL)
            ->assertForbidden();

        $this->assertStringContainsString(
            $material->closes_at->format('d/m/Y H:i'),
            (string) PerpustakaanLiterasiMaterialResource::scheduleWindowHtml($material),
        );

        $this->actingAs($admin)
            ->get(PerpustakaanLiterasiMaterialResource::getUrl('student-history'))
            ->assertOk()
            ->assertSee('History Pengerjaan Siswa')
            ->assertSee('Akun Penilai Aktif');

        $this->actingAs($admin)
            ->get(PerpustakaanLiterasiMaterialResource::getUrl('view', ['record' => $material]))
            ->assertOk()
            ->assertSee('Rekap Bulan Ini')
            ->assertSee('Jumlah Soal')
            ->assertSee('Daftar Soal')
            ->assertSee('Judul soal panel analisa literasi')
            ->assertSee('Kunci Jawaban')
            ->assertSee('Kunci jawaban panel analisa literasi')
            ->assertSee('Indikasi Kemiripan Per Kelas')
            ->assertSee('Indikasi Kemiripan Per Siswa')
            ->assertDontSee('Analisa Per Kelas')
            ->assertDontSee('Ringkasan Plagiat Per Kelas')
            ->assertDontSee('Ranking Kelas Terbanyak Mengisi')
            ->assertDontSee('Ranking 3 Kelas Tersedikit Mengisi');
    }

    public function test_literasi_material_table_actions_update_category_tatib_and_active_status(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = User::query()->create([
            'name' => 'Admin Aksi Literasi',
            'username' => 'admin-aksi-literasi',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $material = $this->createMaterial('Materi Aksi Tabel Literasi');

        $this->actingAs($admin)
            ->get(PerpustakaanLiterasiMaterialResource::getUrl('create'))
            ->assertOk()
            ->assertSee('Wajibkan verifikasi siswa');

        Livewire::actingAs($admin)
            ->test(ListPerpustakaanLiterasiMaterials::class)
            ->assertTableActionVisible('setProgramCategory', $material)
            ->assertTableActionVisible('setInstructions', $material)
            ->assertTableActionVisible('toggleActive', $material)
            ->callTableAction('setInstructions', $material, [
                'instructions' => "Baca arahan dari admin.\nJangan keluar halaman.",
            ])
            ->callTableAction('setProgramCategory', $material, [
                'program_category' => PerpustakaanLiterasiMaterial::CATEGORY_SIGAP_29_KARAKTER,
            ]);

        Livewire::actingAs($admin)
            ->test(ListPerpustakaanLiterasiMaterials::class)
            ->set('activeTab', PerpustakaanLiterasiMaterial::CATEGORY_SIGAP_29_KARAKTER)
            ->call('loadTable')
            ->assertCanSeeTableRecords([$material])
            ->callTableAction('toggleActive', $material);

        $material->refresh();

        $this->assertSame(PerpustakaanLiterasiMaterial::CATEGORY_SIGAP_29_KARAKTER, $material->program_category);
        $this->assertSame("Baca arahan dari admin.\nJangan keluar halaman.", $material->instructions);
        $this->assertFalse($material->is_active);
    }

    public function test_expired_literasi_material_moves_from_active_tab_to_inactive_tab(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = User::query()->create([
            'name' => 'Admin Jadwal Literasi',
            'username' => 'admin-jadwal-literasi',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $active = $this->createMaterial('Materi Masih Aktif', [
            'closes_at' => now()->addHour(),
        ]);
        $expired = $this->createMaterial('Materi Sudah Lewat Durasi', [
            'is_active' => true,
            'closes_at' => now()->subMinute(),
        ]);
        $manualInactive = $this->createMaterial('Materi Nonaktif Manual', [
            'is_active' => false,
            'closes_at' => now()->addHour(),
        ]);

        Livewire::actingAs($admin)
            ->test(ListPerpustakaanLiterasiMaterials::class)
            ->assertSet('activeTab', PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION)
            ->assertSet('materialStatus', 'active')
            ->call('loadTable')
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$expired, $manualInactive])
            ->call('setMaterialStatus', 'inactive')
            ->assertSet('materialStatus', 'inactive')
            ->call('loadTable')
            ->assertCanSeeTableRecords([$expired, $manualInactive])
            ->assertCanNotSeeTableRecords([$active]);
    }

    public function test_literacy_material_cannot_be_saved_without_valid_category(): void
    {
        try {
            PerpustakaanLiterasiMaterial::query()->create([
                'title' => 'Materi Tanpa Kategori',
                'reading_content' => 'Materi ini tidak boleh tersimpan tanpa kategori.',
                'program_category' => null,
                'is_active' => true,
            ]);

            $this->fail('Materi tanpa kategori seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Kategori soal wajib dipilih sebelum materi dapat disimpan.',
                $exception->errors()['program_category'][0] ?? null,
            );
        }

        $this->assertDatabaseMissing('perpustakaan_literasi_materials', [
            'title' => 'Materi Tanpa Kategori',
        ]);
    }

    public function test_deleted_literacy_material_moves_student_history_to_deleted_and_restore_brings_responses_back(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = User::query()->create([
            'name' => 'Admin Restore Literasi',
            'username' => 'admin-restore-literasi',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $studentA = $this->createStudent('Codex Restore A', 'X Restore');
        $studentB = $this->createStudent('Codex Restore B', 'X Restore');
        $material = $this->createMaterial('Materi Restore History');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Soal restore history.',
            'max_characters' => 500,
        ]);
        $responseA = $this->createResponseWithAnswer($material, $studentA, $question, 'Jawaban restore pertama.');
        $responseB = $this->createResponseWithAnswer($material, $studentB, $question, 'Jawaban restore kedua.');
        $answerA = $responseA->answers()->firstOrFail();
        $answerB = $responseB->answers()->firstOrFail();
        $match = PerpustakaanLiterasiSimilarityMatch::query()->create([
            'material_id' => $material->getKey(),
            'question_id' => $question->getKey(),
            'later_response_id' => $responseB->getKey(),
            'matched_response_id' => $responseA->getKey(),
            'later_answer_id' => $answerB->getKey(),
            'matched_answer_id' => $answerA->getKey(),
            'student_class_snapshot' => 'X Restore',
            'similarity_score' => 88.5,
            'later_submitted_at' => now(),
            'matched_submitted_at' => now()->subMinute(),
        ]);

        $material->delete();

        $this->assertSoftDeleted('perpustakaan_literasi_materials', ['id' => $material->getKey()]);
        $this->assertSoftDeleted('perpustakaan_literasi_questions', ['id' => $question->getKey()]);
        $this->assertSoftDeleted('perpustakaan_literasi_responses', ['id' => $responseA->getKey()]);
        $this->assertSoftDeleted('perpustakaan_literasi_responses', ['id' => $responseB->getKey()]);
        $this->assertSoftDeleted('perpustakaan_literasi_answers', ['id' => $answerA->getKey()]);
        $this->assertSoftDeleted('perpustakaan_literasi_answers', ['id' => $answerB->getKey()]);
        $this->assertSoftDeleted('perpustakaan_literasi_similarity_matches', ['id' => $match->getKey()]);

        $this->assertSame(0, PerpustakaanLiterasiMaterial::query()->count());
        $this->assertSame(0, PerpustakaanLiterasiResponse::query()->count());
        $this->assertSame(2, PerpustakaanLiterasiResponse::onlyTrashed()->count());

        $this->actingAs($admin)
            ->get(PerpustakaanLiterasiMaterialResource::getUrl('student-history'))
            ->assertOk()
            ->assertSee('History Terhapus')
            ->assertSee('Materi Terhapus')
            ->assertSee('Restore Materi Terhapus');

        Livewire::actingAs($admin)
            ->test(StudentHistoryPerpustakaanLiterasi::class)
            ->call('restoreDeletedMaterial', $material->getKey());

        $this->assertFalse(PerpustakaanLiterasiMaterial::withTrashed()->findOrFail($material->getKey())->trashed());
        $this->assertFalse(PerpustakaanLiterasiQuestion::withTrashed()->findOrFail($question->getKey())->trashed());
        $this->assertFalse(PerpustakaanLiterasiResponse::withTrashed()->findOrFail($responseA->getKey())->trashed());
        $this->assertFalse(PerpustakaanLiterasiResponse::withTrashed()->findOrFail($responseB->getKey())->trashed());
        $this->assertFalse(PerpustakaanLiterasiAnswer::withTrashed()->findOrFail($answerA->getKey())->trashed());
        $this->assertFalse(PerpustakaanLiterasiAnswer::withTrashed()->findOrFail($answerB->getKey())->trashed());
        $this->assertFalse(PerpustakaanLiterasiSimilarityMatch::withTrashed()->findOrFail($match->getKey())->trashed());
    }

    public function test_orphaned_literacy_history_can_be_soft_deleted_and_restored(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = User::query()->create([
            'name' => 'Admin History Orphan',
            'username' => 'admin-history-orphan',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $student = $this->createStudent('Codex Orphan Student', 'X Orphan');
        $material = $this->createMaterial('Materi Lama Hilang');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Soal materi lama hilang.',
            'max_characters' => 500,
        ]);
        $response = $this->createResponseWithAnswer($material, $student, $question, 'Jawaban pada materi yang hilang.');
        $answer = $response->answers()->firstOrFail();

        DB::table('perpustakaan_literasi_materials')
            ->where('id', $material->getKey())
            ->delete();

        $this->assertNull(PerpustakaanLiterasiMaterial::withTrashed()->find($material->getKey()));
        $this->assertSame(1, PerpustakaanLiterasiResponse::query()->doesntHave('material')->count());
        $this->assertSame(0, PerpustakaanLiterasiResponse::onlyTrashed()->count());

        $this->actingAs($admin)
            ->get(PerpustakaanLiterasiMaterialResource::getUrl('student-history'))
            ->assertOk()
            ->assertSee('History Tanpa Materi')
            ->assertSee('Hapus History Tanpa Materi');

        Livewire::actingAs($admin)
            ->test(StudentHistoryPerpustakaanLiterasi::class)
            ->call('deleteOrphanedHistories');

        $this->assertSoftDeleted('perpustakaan_literasi_responses', ['id' => $response->getKey()]);
        $this->assertSoftDeleted('perpustakaan_literasi_answers', ['id' => $answer->getKey()]);
        $this->assertSame(0, PerpustakaanLiterasiResponse::query()->doesntHave('material')->count());
        $this->assertSame(1, PerpustakaanLiterasiResponse::onlyTrashed()->doesntHave('material')->count());

        Livewire::actingAs($admin)
            ->test(StudentHistoryPerpustakaanLiterasi::class)
            ->call('restoreHistoryResponse', $response->getKey());

        $this->assertFalse(PerpustakaanLiterasiResponse::withTrashed()->findOrFail($response->getKey())->trashed());
        $this->assertFalse(PerpustakaanLiterasiAnswer::withTrashed()->findOrFail($answer->getKey())->trashed());
        $this->assertNull(PerpustakaanLiterasiMaterial::withTrashed()->find($material->getKey()));
        $this->assertSame(1, PerpustakaanLiterasiResponse::query()->doesntHave('material')->count());
    }

    public function test_deleted_literacy_history_can_be_permanently_deleted_with_related_records(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = User::query()->create([
            'name' => 'Admin Force Delete History',
            'username' => 'admin-force-delete-history',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $studentA = $this->createStudent('Codex Force A', 'X Force');
        $studentB = $this->createStudent('Codex Force B', 'X Force');
        $material = $this->createMaterial('Materi Force Delete History');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Soal force delete history.',
            'max_characters' => 500,
        ]);
        $responseA = $this->createResponseWithAnswer($material, $studentA, $question, 'Jawaban pembanding force.');
        $responseB = $this->createResponseWithAnswer($material, $studentB, $question, 'Jawaban target force.');
        $answerA = $responseA->answers()->firstOrFail();
        $answerB = $responseB->answers()->firstOrFail();
        $match = PerpustakaanLiterasiSimilarityMatch::query()->create([
            'material_id' => $material->getKey(),
            'question_id' => $question->getKey(),
            'later_response_id' => $responseB->getKey(),
            'matched_response_id' => $responseA->getKey(),
            'later_answer_id' => $answerB->getKey(),
            'matched_answer_id' => $answerA->getKey(),
            'student_class_snapshot' => 'X Force',
            'similarity_score' => 76.25,
            'later_submitted_at' => now(),
            'matched_submitted_at' => now()->subMinute(),
        ]);

        Livewire::actingAs($admin)
            ->test(StudentHistoryPerpustakaanLiterasi::class)
            ->call('deleteHistoryResponse', $responseB->getKey());

        $this->assertSoftDeleted('perpustakaan_literasi_responses', ['id' => $responseB->getKey()]);
        $this->assertSoftDeleted('perpustakaan_literasi_answers', ['id' => $answerB->getKey()]);
        $this->assertSoftDeleted('perpustakaan_literasi_similarity_matches', ['id' => $match->getKey()]);

        Livewire::actingAs($admin)
            ->test(StudentHistoryPerpustakaanLiterasi::class)
            ->call('forceDeleteHistoryResponse', $responseB->getKey());

        $this->assertNull(PerpustakaanLiterasiResponse::withTrashed()->find($responseB->getKey()));
        $this->assertNull(PerpustakaanLiterasiAnswer::withTrashed()->find($answerB->getKey()));
        $this->assertNull(PerpustakaanLiterasiSimilarityMatch::withTrashed()->find($match->getKey()));
        $this->assertNotNull(PerpustakaanLiterasiResponse::withTrashed()->find($responseA->getKey()));
        $this->assertNotNull(PerpustakaanLiterasiAnswer::withTrashed()->find($answerA->getKey()));
    }

    protected function runLiteracyProgramMigration(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_materials')) {
            return;
        }

        $migration = require database_path('migrations/2026_05_11_120000_create_perpustakaan_literasi_program_tables.php');
        $migration->up();
    }

    protected function runLiteracyQuestionSettingsMigration(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_questions')
            && Schema::hasColumn('perpustakaan_literasi_questions', 'plagiarism_detection_enabled')
            && Schema::hasColumn('perpustakaan_literasi_questions', 'answer_key')) {
            return;
        }

        $migration = require database_path('migrations/2026_06_04_090000_add_answer_key_and_plagiarism_toggle_to_perpustakaan_literasi_questions_table.php');
        $migration->up();
    }

    protected function runLiteracyGradingMigration(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_answers')
            && Schema::hasColumn('perpustakaan_literasi_answers', 'is_correct')) {
            return;
        }

        $migration = require database_path('migrations/2026_05_12_090000_add_grading_to_perpustakaan_literasi_answers_table.php');
        $migration->up();
    }

    protected function runLiteracySimilarityReviewMigration(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_similarity_matches')
            && Schema::hasColumn('perpustakaan_literasi_similarity_matches', 'review_status')) {
            return;
        }

        $migration = require database_path('migrations/2026_05_12_101500_add_review_status_to_perpustakaan_literasi_similarity_matches_table.php');
        $migration->up();
    }

    protected function runLiteracySoftDeletesMigration(): void
    {
        $tables = [
            'perpustakaan_literasi_materials',
            'perpustakaan_literasi_questions',
            'perpustakaan_literasi_responses',
            'perpustakaan_literasi_answers',
            'perpustakaan_literasi_similarity_matches',
        ];

        if (collect($tables)->every(fn (string $table): bool => Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at'))) {
            return;
        }

        $migration = require database_path('migrations/2026_07_01_090000_add_soft_deletes_to_perpustakaan_literasi_tables.php');
        $migration->up();
    }

    protected function runLiterasiNumerasiProgramMigration(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_materials')
            && Schema::hasColumn('perpustakaan_literasi_materials', 'program_category')
            && Schema::hasColumn('perpustakaan_literasi_materials', 'video_url')
            && Schema::hasColumn('perpustakaan_literasi_responses', 'tab_switch_count')) {
            return;
        }

        $migration = require database_path('migrations/2026_07_07_090000_add_literasi_numerasi_program_fields.php');
        $migration->up();
    }

    protected function runLiteracyInstructionsMigration(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_materials')
            && Schema::hasColumn('perpustakaan_literasi_materials', 'instructions')) {
            return;
        }

        $migration = require database_path('migrations/2026_07_08_090000_add_instructions_to_perpustakaan_literasi_materials.php');
        $migration->up();
    }

    protected function runLiteracyStudentVerificationSettingMigration(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_materials')
            && Schema::hasColumn('perpustakaan_literasi_materials', 'student_verification_enabled')) {
            return;
        }

        $migration = require database_path('migrations/2026_07_08_120000_add_student_verification_setting_to_perpustakaan_literasi_materials.php');
        $migration->up();
    }

    protected function runLiteracySubmissionQueueMigration(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_submission_tickets')
            || ! Schema::hasTable('perpustakaan_literasi_submission_queue_states')
            || ! Schema::hasColumn('perpustakaan_literasi_responses', 'similarity_analysis_status')) {
            $migration = require database_path('migrations/2026_07_21_120000_add_literacy_submission_queue_and_analysis_status.php');
            $migration->up();
        }

        if (! Schema::hasColumn('perpustakaan_literasi_submission_queue_states', 'last_submission_activity_at')) {
            $migration = require database_path('migrations/2026_07_22_090000_add_activity_to_literacy_submission_queue_states.php');
            $migration->up();
        }
    }

    protected function runLiteracySubmissionDeliveryMigration(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_responses')
            && Schema::hasColumn('perpustakaan_literasi_responses', 'submission_delivery_code')
            && Schema::hasColumn('perpustakaan_literasi_responses', 'submission_queue_wait_seconds')
            && Schema::hasColumn('perpustakaan_literasi_responses', 'submission_retry_statuses')) {
            return;
        }

        $migration = require database_path('migrations/2026_07_23_193000_add_submission_delivery_status_to_literacy_responses.php');
        $migration->up();
    }

    protected function runLiteracySubmissionRequestKeyMigration(): void
    {
        if (Schema::hasColumn('perpustakaan_literasi_submission_tickets', 'request_key_hash')) {
            return;
        }

        $migration = require database_path('migrations/2026_08_06_100000_add_request_key_hash_to_literacy_submission_tickets.php');
        $migration->up();
    }

    protected function runLiteracyOperationalMonitoringMigration(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_submission_events')
            && Schema::hasTable('perpustakaan_literasi_network_checks')
            && Schema::hasColumn('perpustakaan_literasi_submission_queue_states', 'scheduler_heartbeat_at')) {
            return;
        }

        $migration = require database_path('migrations/2026_07_28_080000_add_literacy_operational_monitoring.php');
        $migration->up();
    }

    protected function runLiteracyObjectiveQuestionMigration(): void
    {
        if (Schema::hasColumn('perpustakaan_literasi_questions', 'question_type')
            && Schema::hasColumn('perpustakaan_literasi_answers', 'score_earned')) {
            return;
        }

        $migration = require database_path('migrations/2026_07_29_080000_add_objective_question_types_to_literacy.php');
        $migration->up();
    }

    protected function runLiteracyDispensationMigration(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_dispensations')) {
            return;
        }

        $migration = require database_path('migrations/2026_07_30_090000_create_perpustakaan_literasi_dispensations_table.php');
        $migration->up();
    }

    protected function makeTestPng(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($image, 22, 163, 74);
        imagefill($image, 0, 0, $background);

        ob_start();
        imagepng($image);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return $contents;
    }

    protected function bootstrapPengaturanTable(): void
    {
        if (Schema::hasTable('pengaturan')) {
            return;
        }

        Schema::create('pengaturan', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_pengaturan')->unique();
            $table->text('nilai_pengaturan')->nullable();
        });

        Pengaturan::flushRuntimeSchemaCache();
    }

    protected function literacyQueueRequest(string $sessionId): HttpRequest
    {
        $request = HttpRequest::create('/perpustakaan/program-literasi-numerasi/antrean', 'POST');
        $session = new Store('literacy-queue-test', new ArraySessionHandler(120));
        $session->setId($sessionId);
        $session->start();
        $request->setLaravelSession($session);

        return $request;
    }

    protected function bootstrapLibraryHubTables(): void
    {
        if (! Schema::hasTable('perpustakaan_kategori')) {
            Schema::create('perpustakaan_kategori', function (Blueprint $table): void {
                $table->id();
                $table->string('nama_kategori')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('perpustakaan_lemari')) {
            Schema::create('perpustakaan_lemari', function (Blueprint $table): void {
                $table->id();
                $table->string('nama_lemari')->nullable();
                $table->string('lokasi')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('perpustakaan_buku')) {
            Schema::create('perpustakaan_buku', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('kategori_id')->nullable();
                $table->unsignedBigInteger('lemari_id')->nullable();
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
    }

    protected function createMaterial(string $title, array $attributes = []): PerpustakaanLiterasiMaterial
    {
        return PerpustakaanLiterasiMaterial::query()->create(array_merge([
            'title' => $title,
            'reading_content' => 'Bacaan literasi untuk pengujian fitur.',
            'program_category' => PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION,
            'is_active' => true,
        ], $attributes));
    }

    protected function createStudent(string $name, string $class, array $attributes = []): DataSiswa
    {
        return DataSiswa::query()->create(array_merge([
            'nama' => $name,
            'rombel_saat_ini' => $class,
            'status' => 'aktif',
        ], $attributes));
    }

    protected function createResponseWithAnswer(
        PerpustakaanLiterasiMaterial $material,
        DataSiswa $student,
        $question,
        string $answerText,
        $submittedAt = null
    ): PerpustakaanLiterasiResponse {
        $submittedAt ??= now();

        $response = PerpustakaanLiterasiResponse::query()->create([
            'material_id' => $material->getKey(),
            'data_siswa_id' => $student->getKey(),
            'student_name_snapshot' => $student->nama,
            'student_class_snapshot' => $student->rombel_saat_ini,
            'submitted_at' => $submittedAt,
        ]);

        PerpustakaanLiterasiAnswer::query()->create([
            'response_id' => $response->getKey(),
            'question_id' => $question->getKey(),
            'answer_text' => $answerText,
            'character_count' => mb_strlen($answerText),
        ]);

        return $response->load('answers');
    }
}
