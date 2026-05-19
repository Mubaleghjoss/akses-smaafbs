<?php

namespace Tests\Feature;

use App\Models\PerpustakaanLiterasiActivity;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LibraryLiteracyActivityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->bootstrapLibraryTables();
    }

    protected function tearDown(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_activities')) {
            PerpustakaanLiterasiActivity::query()
                ->where('participant_name', 'like', 'Codex Test%')
                ->delete();
        }

        if (Schema::hasTable('data_siswa')) {
            DB::table('data_siswa')
                ->where('nama', 'like', 'Codex Test%')
                ->delete();
        }

        if (Schema::hasTable('guru_tendik')) {
            DB::table('guru_tendik')
                ->where('nama', 'like', 'Codex Test%')
                ->delete();
        }

        parent::tearDown();
    }

    public function test_literacy_activity_pages_are_reachable(): void
    {
        $this->get(route('library.activities'))->assertOk();
        $this->get(route('library.activities.create'))->assertOk();
        $this->get(route('library.activities.result'))->assertOk();
    }

    public function test_student_literacy_activity_can_be_created_and_result_submitted(): void
    {
        $this->post(route('library.activities.store'), [
            'purpose' => PerpustakaanLiterasiActivity::PURPOSE_LITERASI,
            'participant_role' => 'siswa',
            'participant_name' => 'Codex Test Siswa Literasi',
            'participant_class' => 'X A',
            'book_title' => 'Bumi Manusia',
            'book_author' => 'Pramoedya Ananta Toer',
        ])->assertRedirect();

        $activity = PerpustakaanLiterasiActivity::query()
            ->where('participant_name', 'Codex Test Siswa Literasi')
            ->firstOrFail();

        $this->assertSame(PerpustakaanLiterasiActivity::RESULT_PENDING, $activity->result_status);
        $this->assertSame('Bumi Manusia', $activity->book_title_snapshot);

        $this->post(route('library.activities.result.store'), [
            'activity_code' => $activity->activity_code,
            'result_text' => 'Saya memahami tokoh utama, latar cerita, dan pesan penting dari buku yang dibaca.',
        ])->assertRedirect(route('library.activities.result', ['code' => Str::afterLast($activity->activity_code, '-')]));

        $this->assertDatabaseHas('perpustakaan_literasi_activities', [
            'activity_code' => $activity->activity_code,
            'result_status' => PerpustakaanLiterasiActivity::RESULT_SUBMITTED,
        ]);
    }

    public function test_result_can_be_edited_with_short_code(): void
    {
        $this->post(route('library.activities.store'), [
            'purpose' => PerpustakaanLiterasiActivity::PURPOSE_LITERASI,
            'participant_role' => 'siswa',
            'participant_name' => 'Codex Test Siswa Kode Pendek',
            'participant_class' => 'XII A',
            'book_title' => 'Buku Kode Pendek',
        ])->assertRedirect();

        $activity = PerpustakaanLiterasiActivity::query()
            ->where('participant_name', 'Codex Test Siswa Kode Pendek')
            ->firstOrFail();

        $shortCode = Str::afterLast($activity->activity_code, '-');

        $this->get(route('library.activities.result', ['code' => $shortCode]))
            ->assertOk()
            ->assertSee($shortCode)
            ->assertSee($activity->activity_code);

        $this->post(route('library.activities.result.store'), [
            'activity_code' => $shortCode,
            'result_text' => 'Ini adalah hasil bacaan yang diedit memakai kode singkat dari aktivitas.',
        ])->assertRedirect(route('library.activities.result', ['code' => $shortCode]));

        $this->get(route('library.activities.result', ['code' => $shortCode]))
            ->assertOk()
            ->assertSee('Ini adalah hasil bacaan yang diedit memakai kode singkat dari aktivitas.');

        $this->getJson(route('library.activities.result.lookup', ['code' => $shortCode]))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('short_code', $shortCode)
            ->assertJsonPath('result_text', 'Ini adalah hasil bacaan yang diedit memakai kode singkat dari aktivitas.');

        $this->assertDatabaseHas('perpustakaan_literasi_activities', [
            'activity_code' => $activity->activity_code,
            'result_text' => 'Ini adalah hasil bacaan yang diedit memakai kode singkat dari aktivitas.',
            'result_status' => PerpustakaanLiterasiActivity::RESULT_SUBMITTED,
        ]);
    }

    public function test_activity_list_does_not_expose_edit_code(): void
    {
        $this->post(route('library.activities.store'), [
            'purpose' => PerpustakaanLiterasiActivity::PURPOSE_LITERASI,
            'participant_role' => 'siswa',
            'participant_name' => 'Codex Test Siswa Kode Rahasia',
            'participant_class' => 'XI C',
            'book_title' => 'Buku Kode Rahasia',
        ])->assertRedirect();

        $activity = PerpustakaanLiterasiActivity::query()
            ->where('participant_name', 'Codex Test Siswa Kode Rahasia')
            ->firstOrFail();

        $this->get(route('library.activities'))
            ->assertOk()
            ->assertSee('Buku Kode Rahasia')
            ->assertDontSee($activity->activity_code)
            ->assertDontSee(Str::afterLast($activity->activity_code, '-'));
    }

    public function test_student_activity_can_use_master_student_data(): void
    {
        DB::table('data_siswa')->insert([
            'nama' => 'Codex Test Master Siswa',
            'rombel_saat_ini' => 'XI B',
            'status' => 'aktif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $studentId = (int) DB::table('data_siswa')
            ->where('nama', 'Codex Test Master Siswa')
            ->value('id');

        $this->post(route('library.activities.store'), [
            'purpose' => PerpustakaanLiterasiActivity::PURPOSE_LITERASI,
            'participant_role' => 'siswa',
            'participant_id' => $studentId,
            'book_title' => 'Buku dari Data Siswa',
        ])->assertRedirect();

        $this->assertDatabaseHas('perpustakaan_literasi_activities', [
            'participant_id' => $studentId,
            'participant_name' => 'Codex Test Master Siswa',
            'participant_class' => 'XI B',
        ]);
    }

    public function test_teacher_literacy_activity_does_not_require_hidden_class_field(): void
    {
        DB::table('guru_tendik')->insert([
            'nama' => 'Codex Test Master Guru',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $teacherId = (int) DB::table('guru_tendik')
            ->where('nama', 'Codex Test Master Guru')
            ->value('id');

        $this->post(route('library.activities.store'), [
            'purpose' => PerpustakaanLiterasiActivity::PURPOSE_LITERASI,
            'participant_role' => 'guru',
            'participant_id' => $teacherId,
            'participant_class' => '',
            'subject_name' => '',
            'book_title' => 'Indahnya Menjadi Pendengar',
            'book_author' => 'Mr. Putra',
        ])->assertRedirect();

        $this->assertDatabaseHas('perpustakaan_literasi_activities', [
            'participant_id' => $teacherId,
            'participant_name' => 'Codex Test Master Guru',
            'participant_class' => null,
            'book_title_snapshot' => 'Indahnya Menjadi Pendengar',
        ]);
    }

    public function test_task_activity_requires_subject_and_marks_result_not_required(): void
    {
        $payload = [
            'purpose' => PerpustakaanLiterasiActivity::PURPOSE_TUGAS,
            'participant_role' => 'guru',
            'participant_name' => 'Codex Test Guru Mapel',
            'book_title' => 'Ensiklopedia Sains',
        ];

        $this->post(route('library.activities.store'), $payload)
            ->assertSessionHasErrors(['subject_name']);

        $this->post(route('library.activities.store'), $payload + [
            'subject_name' => 'IPA',
        ])->assertRedirect();

        $this->assertDatabaseHas('perpustakaan_literasi_activities', [
            'participant_name' => 'Codex Test Guru Mapel',
            'subject_name' => 'IPA',
            'result_status' => PerpustakaanLiterasiActivity::RESULT_NOT_REQUIRED,
        ]);
    }

    private function bootstrapLibraryTables(): void
    {
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

        if (! Schema::hasTable('perpustakaan_literasi_activities')) {
            Schema::create('perpustakaan_literasi_activities', function (Blueprint $table): void {
                $table->id();
                $table->string('activity_code', 40)->unique();
                $table->string('purpose', 30)->index();
                $table->unsignedBigInteger('participant_id')->nullable()->index();
                $table->string('participant_name', 150)->index();
                $table->string('participant_class', 100)->nullable()->index();
                $table->string('participant_role', 50)->nullable();
                $table->unsignedBigInteger('book_id')->nullable()->index();
                $table->string('book_title_snapshot', 500)->index();
                $table->string('book_author_snapshot', 255)->nullable();
                $table->string('subject_name', 150)->nullable()->index();
                $table->dateTime('activity_at')->nullable()->index();
                $table->string('result_status', 30)->default('pending')->index();
                $table->longText('result_text')->nullable();
                $table->dateTime('result_submitted_at')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('perpustakaan_literasi_activities')
            && ! Schema::hasColumn('perpustakaan_literasi_activities', 'participant_id')) {
            Schema::table('perpustakaan_literasi_activities', function (Blueprint $table): void {
                $table->unsignedBigInteger('participant_id')->nullable()->index()->after('purpose');
            });
        }

        if (! Schema::hasTable('data_siswa')) {
            Schema::create('data_siswa', function (Blueprint $table): void {
                $table->id();
                $table->string('nama')->nullable();
                $table->string('rombel_saat_ini')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('guru_tendik')) {
            Schema::create('guru_tendik', function (Blueprint $table): void {
                $table->id();
                $table->string('nama')->nullable();
                $table->string('jenis_ptk')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }
    }
}
