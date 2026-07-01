<?php

namespace Tests\Feature;

use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource\Pages\StudentHistoryPerpustakaanLiterasi;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource\Pages\ViewPerpustakaanLiterasiMaterial;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource\RelationManagers\ResponsesRelationManager;
use App\Models\DataSiswa;
use App\Models\PerpustakaanLiterasiAnswer;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiQuestion;
use App\Models\PerpustakaanLiterasiResponse;
use App\Models\PerpustakaanLiterasiSimilarityMatch;
use App\Models\User;
use App\Support\Admin\AdminModuleAccess;
use App\Support\Perpustakaan\LiterasiAnalytics;
use Filament\Facades\Filament;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            ->assertSee('Literacy Habituation Program')
            ->assertSee('Materi Aktif Literasi')
            ->assertSee('Tutup '.$active->closes_at->format('d/m/Y H:i'))
            ->assertSee('tex-chtml.js', false)
            ->assertSee('\(x^{2}\)', false)
            ->assertSee('\(\frac{a}{b}\)', false)
            ->assertDontSee('Materi Nonaktif')
            ->assertDontSee('Materi Masa Depan');
    }

    public function test_literasi_shortcut_redirects_to_literacy_program(): void
    {
        $this->get('/literasi')
            ->assertRedirect('/perpustakaan/literacy-habituation-program');
    }

    public function test_public_literacy_images_normalize_legacy_filename_only_paths(): void
    {
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

        $this->get(route('library.literacy.show', $material->slug))
            ->assertOk()
            ->assertSee('/storage/literasi/materials/legacy-material.png', false)
            ->assertSee('/storage/literasi/questions/legacy-question.png', false);
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
            ->assertSee('Kode edit jawaban')
            ->assertSee('Buka Edit')
            ->assertSee('data-literacy-student-combobox', false)
            ->assertSee('data-literacy-answer-count', false);

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
            ->assertSee('data-literacy-answer-count', false);

        $this->post(route('library.literacy.update', $response->shortEditCode()), [
            'answers' => [
                $question->getKey() => 'Jawaban saya sudah diedit dengan tambahan refleksi setelah membaca ulang materi.',
            ],
        ])->assertRedirect(route('library.literacy.edit', $response->shortEditCode()));

        $this->assertDatabaseHas('perpustakaan_literasi_answers', [
            'response_id' => $response->getKey(),
            'question_id' => $question->getKey(),
            'answer_text' => 'Jawaban saya sudah diedit dengan tambahan refleksi setelah membaca ulang materi.',
        ]);
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
            ->assertSessionHasErrors(['student_id']);
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

        $this->post(route('library.literacy.store', $material->slug), [
            'student_id' => $student->getKey(),
            'answers' => [
                $question->getKey() => 'Jawaban ini melebihi batas karakter yang sudah ditentukan.',
            ],
        ])->assertSessionHasErrors(['answers.'.$question->getKey()]);
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

    public function test_exact_short_answers_are_flagged_when_plagiarism_detection_is_enabled(): void
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

        $this->assertDatabaseHas('perpustakaan_literasi_similarity_matches', [
            'material_id' => $material->getKey(),
            'question_id' => $question->getKey(),
            'later_response_id' => $responseB->getKey(),
            'similarity_score' => 100.00,
        ]);
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
            ->assertSee('Aktifkan deteksi plagiasi')
            ->assertSee('Jawaban sama persis selalu muncul sebagai 100%')
            ->assertSee('Mode deteksi aktif: jawaban yang sama dengan kunci otomatis Benar')
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
            ]);

        $schemaMethod = new \ReflectionMethod(ResponsesRelationManager::class, 'gradingFormSchema');
        $schemaMethod->setAccessible(true);
        $gradingSchema = $schemaMethod->invoke($gradingComponent->instance(), $response);
        $childComponents = new \ReflectionProperty($gradingSchema[0], 'childComponents');
        $childComponents->setAccessible(true);
        $firstQuestionComponents = $childComponents->getValue($gradingSchema[0])['default'];
        $answerKeyPlaceholder = collect($firstQuestionComponents)
            ->first(fn ($component): bool => method_exists($component, 'getName')
                && $component->getName() === 'answer_'.$answer->getKey().'_answer_key');

        $this->assertNotNull($answerKeyPlaceholder);
        $answerKeyState = new \ReflectionProperty($answerKeyPlaceholder, 'getConstantStateUsing');
        $answerKeyState->setAccessible(true);
        $answerKeyContent = strip_tags((string) $answerKeyState->getValue($answerKeyPlaceholder));

        $this->assertStringContainsString('Jawaban kunci untuk dicek guru.', $answerKeyContent);

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
        ])->assertRedirect(route('library.literacy.edit', $response->shortEditCode()));

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

        $oldResponse = $this->createResponseWithAnswer($material, $this->createStudent('Codex Ranking Old', 'X Ranking'), $questionOne, 'Jawaban bulan lalu.', now()->subMonth());
        $oldResponse->answers()->firstOrFail()->forceFill(['is_correct' => true, 'graded_at' => now()->subMonth()])->save();

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
        $this->assertSame(1, (int) $plagiarismStudents['Codex Ranking B']['total']);
        $this->assertSame(1, (int) $plagiarismStudents['Codex Ranking C']['total']);
        $this->assertFalse($plagiarismStudents->has('Codex Ranking A'));
        $this->assertSame(3, (int) $analytics['grading_summary']['responses']);
        $this->assertSame(2, (int) $analytics['grading_summary']['correct_answers']);
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
            ->assertSee('Analisa Total Literasi')
            ->assertSee('History Pengerjaan Siswa')
            ->assertSee('Hitung Ulang Plagiasi')
            ->assertSee('Ranking Kelas Terbanyak Mengisi')
            ->assertSee('Ranking 3 Kelas Jawaban Benar Terbanyak')
            ->assertSee('Ranking 3 Kelas Tersedikit Mengisi')
            ->assertSee('Ranking Siswa Per Kelas Berdasarkan Jawaban Benar');

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
            ->assertSee('Daftar Plagiat Per Kelas')
            ->assertSee('Daftar Plagiat Per Siswa')
            ->assertDontSee('Analisa Per Kelas')
            ->assertDontSee('Ringkasan Plagiat Per Kelas')
            ->assertDontSee('Ranking Kelas Terbanyak Mengisi')
            ->assertDontSee('Ranking 3 Kelas Tersedikit Mengisi');
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

    protected function createMaterial(string $title, array $attributes = []): PerpustakaanLiterasiMaterial
    {
        return PerpustakaanLiterasiMaterial::query()->create(array_merge([
            'title' => $title,
            'reading_content' => 'Bacaan literasi untuk pengujian fitur.',
            'is_active' => true,
        ], $attributes));
    }

    protected function createStudent(string $name, string $class): DataSiswa
    {
        return DataSiswa::query()->create([
            'nama' => $name,
            'rombel_saat_ini' => $class,
            'status' => 'aktif',
        ]);
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
