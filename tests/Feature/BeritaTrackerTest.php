<?php

namespace Tests\Feature;

use App\Filament\Resources\BeritaResource;
use App\Models\Berita;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema as FilamentSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BeritaTrackerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureBeritaTable();
        $this->ensureBeritaUpdatesTable();
        Berita::flushSchemaColumnAvailabilityCache();
    }

    public function test_berita_tracker_fields_are_cast_and_accessible(): void
    {
        $berita = Berita::query()->create([
            'judul' => 'Tracker Kegiatan QA',
            'konten' => 'Deskripsi utama kegiatan.',
            'status' => 'aktif',
            'tanggal_berita' => '2026-03-31',
            'tracker_phase' => 'acara',
            'tracker_progress_percent' => 65,
            'tracker_update_text' => 'Saat ini masuk sesi presentasi.',
            'tracker_documentation_media' => ['news/documentation/doc-1.jpg', 'news/documentation/doc-2.jpg'],
            'tracker_live_url' => 'https://www.youtube.com/watch?v=abc123',
        ]);

        $fresh = $berita->fresh();

        $this->assertSame('acara', $fresh->tracker_phase);
        $this->assertSame('Acara', $fresh->tracker_phase_label);
        $this->assertSame(65, $fresh->tracker_progress_percent);
        $this->assertSame('Saat ini masuk sesi presentasi.', $fresh->tracker_update_text);
        $this->assertSame(['news/documentation/doc-1.jpg', 'news/documentation/doc-2.jpg'], $fresh->tracker_documentation_media);
        $this->assertSame('https://www.youtube.com/watch?v=abc123', $fresh->tracker_live_url);
    }

    public function test_news_show_renders_tracker_sections_when_present(): void
    {
        $berita = Berita::query()->create([
            'judul' => 'Kegiatan Live Kampus',
            'konten' => 'Kegiatan utama berlangsung sepanjang hari.',
            'gambar' => 'banner.jpg',
            'status' => 'aktif',
            'tanggal_berita' => '2026-03-31',
            'tracker_phase' => 'persiapan',
            'tracker_progress_percent' => 20,
            'tracker_update_text' => 'Panitia sedang menyiapkan panggung.',
            'tracker_documentation_media' => ['doc-1.jpg'],
            'tracker_live_url' => 'https://instagram.com/live/example',
        ]);

        $this->get(route('news.show', $berita))
            ->assertOk()
            ->assertSee('Perkembangan kegiatan')
            ->assertSee('Tahap pelaksanaan')
            ->assertSee('Persiapan')
            ->assertSee('20%')
            ->assertSee('Panitia sedang menyiapkan panggung.')
            ->assertSee('Buka siaran langsung');
    }

    public function test_inactive_news_is_hidden_from_public_listing_and_detail(): void
    {
        $inactive = Berita::query()->create([
            'judul' => 'Kegiatan Internal',
            'konten' => 'Tidak boleh tampil di publik.',
            'status' => 'tidak aktif',
            'tanggal_berita' => '2026-04-01',
        ]);

        $this->get(route('news.index'))
            ->assertOk()
            ->assertDontSee('Kegiatan Internal');

        $this->get(route('news.show', $inactive))
            ->assertNotFound();
    }

    public function test_public_news_routes_remain_accessible_when_tracker_columns_are_missing(): void
    {
        if (Schema::hasTable('berita')) {
            Schema::drop('berita');
        }

        Schema::create('berita', function (Blueprint $table): void {
            $table->id();
            $table->string('judul')->nullable();
            $table->text('konten')->nullable();
            $table->string('gambar')->nullable();
            $table->unsignedBigInteger('id_admin')->nullable();
            $table->string('status')->nullable();
            $table->date('tanggal_berita')->nullable();
            $table->timestamps();
        });

        Berita::flushSchemaColumnAvailabilityCache();

        $active = Berita::query()->create([
            'judul' => 'Kegiatan Umum',
            'konten' => 'Berita tetap bisa dibaca tanpa kolom tracker baru.',
            'status' => 'aktif',
            'tanggal_berita' => '2026-04-02',
        ]);

        Berita::query()->create([
            'judul' => 'Kegiatan Internal',
            'konten' => 'Harus tetap tersembunyi.',
            'status' => 'tidak aktif',
            'tanggal_berita' => '2026-04-01',
        ]);

        $this->get(route('news.index'))
            ->assertOk()
            ->assertSee('Kegiatan Umum')
            ->assertDontSee('Kegiatan Internal');

        $this->get(route('news.show', $active))
            ->assertOk()
            ->assertSee('Kegiatan Umum')
            ->assertDontSee('Perkembangan kegiatan');
    }

    public function test_berita_resource_hides_tracker_section_when_tracker_columns_are_missing(): void
    {
        if (Schema::hasTable('berita')) {
            Schema::drop('berita');
        }

        Schema::create('berita', function (Blueprint $table): void {
            $table->id();
            $table->string('judul')->nullable();
            $table->text('konten')->nullable();
            $table->string('gambar')->nullable();
            $table->unsignedBigInteger('id_admin')->nullable();
            $table->string('status')->nullable();
            $table->date('tanggal_berita')->nullable();
            $table->timestamps();
        });

        Berita::flushSchemaColumnAvailabilityCache();

        $schema = BeritaResource::form(FilamentSchema::make());

        $sectionHeadings = collect($schema->getComponents(withHidden: true))
            ->filter(fn ($component): bool => $component instanceof Section)
            ->map(fn (Section $section): mixed => $section->getHeading())
            ->filter()
            ->values()
            ->all();

        $this->assertContains('Konten Berita', $sectionHeadings);
        $this->assertNotContains('Tracker Kegiatan (Opsional)', $sectionHeadings);
    }

    public function test_berita_update_syncs_latest_tracker_snapshot(): void
    {
        $berita = Berita::query()->create([
            'judul' => 'Kegiatan Wisata',
            'konten' => 'Deskripsi utama kegiatan.',
            'status' => 'aktif',
            'tanggal_berita' => '2026-03-31',
        ]);

        $berita->updates()->create([
            'phase' => 'persiapan',
            'progress_percent' => 25,
            'tanggal_update' => '2026-03-31 08:00:00',
            'update_text' => 'Panitia sedang briefing peserta.',
            'documentation_media' => ['news/documentation/persiapan-1.jpg'],
            'live_url' => null,
        ]);

        $berita->updates()->create([
            'phase' => 'acara',
            'progress_percent' => 60,
            'tanggal_update' => '2026-03-31 10:00:00',
            'update_text' => 'Kegiatan utama sedang berlangsung.',
            'documentation_media' => ['news/documentation/acara-1.jpg'],
            'live_url' => 'https://youtube.com/watch?v=acara123',
        ]);

        $fresh = $berita->fresh();

        $this->assertSame('acara', $fresh->tracker_phase);
        $this->assertSame(60, $fresh->tracker_progress_percent);
        $this->assertSame('Kegiatan utama sedang berlangsung.', $fresh->tracker_update_text);
        $this->assertSame(['news/documentation/acara-1.jpg'], $fresh->tracker_documentation_media);
        $this->assertSame('https://youtube.com/watch?v=acara123', $fresh->tracker_live_url);
    }

    public function test_news_show_renders_full_timeline_updates_when_available(): void
    {
        $berita = Berita::query()->create([
            'judul' => 'Kemah Santri',
            'konten' => 'Kegiatan perkemahan tahunan.',
            'status' => 'aktif',
            'tanggal_berita' => '2026-03-31',
        ]);

        $berita->updates()->create([
            'phase' => 'persiapan',
            'progress_percent' => 20,
            'tanggal_update' => '2026-03-31 07:00:00',
            'update_text' => 'Peserta sedang registrasi ulang.',
            'documentation_media' => ['news/documentation/persiapan-kemah.jpg'],
        ]);

        $berita->updates()->create([
            'phase' => 'selesai',
            'progress_percent' => 100,
            'tanggal_update' => '2026-03-31 18:00:00',
            'update_text' => 'Seluruh rangkaian kegiatan telah selesai.',
            'documentation_media' => ['news/documentation/selesai-kemah.jpg'],
            'live_url' => 'https://instagram.com/live/kemah',
        ]);

        $this->get(route('news.show', $berita))
            ->assertOk()
            ->assertSee('Perkembangan kegiatan')
            ->assertSee('Peserta sedang registrasi ulang.')
            ->assertSee('Seluruh rangkaian kegiatan telah selesai.')
            ->assertSee('Seluruh dokumentasi kegiatan')
            ->assertSee('Buka siaran langsung');
    }

    protected function ensureBeritaTable(): void
    {
        if (! Schema::hasTable('berita')) {
            Schema::create('berita', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('judul')->nullable();
                $table->text('konten')->nullable();
                $table->string('gambar')->nullable();
                $table->unsignedBigInteger('id_admin')->nullable();
                $table->string('status')->nullable();
                $table->date('tanggal_berita')->nullable();
                $table->string('tracker_phase', 20)->nullable();
                $table->unsignedTinyInteger('tracker_progress_percent')->nullable();
                $table->text('tracker_update_text')->nullable();
                $table->json('tracker_documentation_media')->nullable();
                $table->string('tracker_live_url', 2048)->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('berita', function (Blueprint $table): void {
            if (! Schema::hasColumn('berita', 'tracker_phase')) {
                $table->string('tracker_phase', 20)->nullable();
            }

            if (! Schema::hasColumn('berita', 'tracker_progress_percent')) {
                $table->unsignedTinyInteger('tracker_progress_percent')->nullable();
            }

            if (! Schema::hasColumn('berita', 'tracker_update_text')) {
                $table->text('tracker_update_text')->nullable();
            }

            if (! Schema::hasColumn('berita', 'tracker_documentation_media')) {
                $table->json('tracker_documentation_media')->nullable();
            }

            if (! Schema::hasColumn('berita', 'tracker_live_url')) {
                $table->string('tracker_live_url', 2048)->nullable();
            }
        });
    }

    protected function ensureBeritaUpdatesTable(): void
    {
        if (Schema::hasTable('berita_updates')) {
            return;
        }

        Schema::create('berita_updates', function (Blueprint $table): void {
            $table->id();
            $table->integer('berita_id');
            $table->string('phase', 20);
            $table->unsignedTinyInteger('progress_percent')->nullable();
            $table->dateTime('tanggal_update')->nullable();
            $table->text('update_text')->nullable();
            $table->json('documentation_media')->nullable();
            $table->string('live_url', 2048)->nullable();
            $table->timestamps();
        });
    }
}
