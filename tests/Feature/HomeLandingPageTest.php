<?php

namespace Tests\Feature;

use App\Models\Berita;
use App\Models\CalendarEvent;
use App\Models\DataSiswa;
use App\Models\GuruTendik;
use App\Models\ProfilSekolah;
use App\Models\StrukturOrganisasi;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomeLandingPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureDataSiswaTable();
        $this->ensureBeritaTable();
        $this->ensureCalendarEventsTable();
        $this->ensureBeritaUpdatesTable();
        $this->ensureStrukturOrganisasiTable();
        $this->ensureProfilSekolahTable();
        $this->ensureProkersTable();
        $this->ensureProkerBidangsTable();
        $this->ensureProkerMetricColumns();
        $this->ensureDataSiswaHomepageMetricColumns();
        Berita::flushSchemaColumnAvailabilityCache();
    }

    public function test_home_landing_page_renders_student_agenda_and_tracker_sections(): void
    {
        DataSiswa::query()->create([
            'nama' => 'Santri Aktif',
            'nisn' => '1234567890',
            'status' => 'aktif',
            'jk' => 'L',
            'rombel_saat_ini' => 'X-A',
            'tanggal_lahir' => '2010-02-03',
        ]);

        DataSiswa::query()->create([
            'nama' => 'Alumni Hebat',
            'nisn' => '0987654321',
            'status' => 'alumni',
            'jk' => 'P',
            'rombel_saat_ini' => 'XI-B',
            'tanggal_lahir' => '2008-06-15',
        ]);

        DataSiswa::query()->create([
            'nama' => 'Santri Aktif Dua',
            'nisn' => '1234511111',
            'status' => 'aktif',
            'jk' => 'P',
            'rombel_saat_ini' => 'X-B',
            'tanggal_lahir' => '2010-05-10',
        ]);

        GuruTendik::query()->create([
            'nama' => 'Guru Putra',
            'jenis_ptk' => 'Guru',
            'jk' => 'L',
        ]);

        GuruTendik::query()->create([
            'nama' => 'Guru Putri',
            'jenis_ptk' => 'Guru',
            'jk' => 'P',
        ]);

        GuruTendik::query()->create([
            'nama' => 'Tendik Putra',
            'jenis_ptk' => 'Tendik',
            'jk' => 'L',
        ]);

        GuruTendik::query()->create([
            'nama' => 'Tendik Putri',
            'jenis_ptk' => 'Tendik',
            'jk' => 'P',
        ]);

        DB::table('proker_bidangs')->insert([
            ['nama' => 'Kurikulum', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nama' => 'Kesiswaan', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $bidangIds = DB::table('proker_bidangs')->pluck('id')->all();

        DB::table('prokers')->insert([
            ['status' => 'selesai', 'bidang_id' => $bidangIds[0] ?? null, 'created_at' => now(), 'updated_at' => now()],
            ['status' => 'berjalan', 'bidang_id' => $bidangIds[0] ?? null, 'created_at' => now(), 'updated_at' => now()],
            ['status' => 'draft', 'bidang_id' => $bidangIds[1] ?? null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        CalendarEvent::query()->create([
            'title' => 'Wisata Edukasi',
            'description' => 'Kunjungan belajar ke luar kota.',
            'visibility' => 'external',
            'all_day' => false,
            'start' => Carbon::parse('2026-04-10 08:00:00'),
            'end' => Carbon::parse('2026-04-10 16:00:00'),
        ]);

        StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/kepala.jpg',
            'urutan' => 1,
        ]);

        ProfilSekolah::query()->create([
            'title' => 'Identitas Sekolah',
            'nama_sekolah' => 'SMA AFBS',
            'provinsi' => 'Jawa Barat',
            'alamat' => 'Jl. Pendidikan No. 10',
            'kontak_telepon' => '0812-0000-0000',
            'kontak_email' => 'info@afbs.test',
            'tanggal_identitas' => '2024-12-01',
            'tanggal_berdiri' => '2001-07-17',
            'file_akreditasi_path' => 'identitas-sekolah/akreditasi/sk-akreditasi.pdf',
            'website_url' => 'https://afbs.test',
            'maps_url' => 'https://maps.google.com/?q=afbs',
            'identitas_tambahan' => [
                ['label' => 'NPSN', 'value' => '20260001'],
            ],
            'fasilitas' => [
                ['nama' => 'Laboratorium', 'foto' => 'profil-sekolah/fasilitas/lab.jpg', 'keterangan' => 'Lab sains terpadu'],
            ],
            'jadwal_kbm' => [
                ['waktu' => '07.00 - 07.30', 'kegiatan' => 'Apel pagi'],
            ],
            'menu_makan' => [
                ['hari' => 'Senin', 'menu' => 'Nasi, ayam, sayur'],
            ],
        ]);

        $berita = Berita::query()->create([
            'judul' => 'Kegiatan Wisata Santri',
            'konten' => 'Deskripsi utama kegiatan.',
            'status' => 'aktif',
            'tanggal_berita' => '2026-04-10',
            'tracker_phase' => 'persiapan',
            'tracker_progress_percent' => 25,
            'tracker_update_text' => 'Sedang briefing keberangkatan.',
            'tracker_documentation_media' => ['news/documentation/persiapan-1.jpg'],
            'tracker_live_url' => 'https://youtube.com/watch?v=test123',
        ]);

        $response = $this->get(route('home', ['q' => 'Santri']));

        $response
            ->assertOk()
            ->assertSee('Identitas sekolah untuk orang tua, siswa, dan masyarakat.')
            ->assertSee('data-home-profile-tab-trigger="identitas-sekolah"', false)
            ->assertSee('data-home-profile-tab-trigger="komite"', false)
            ->assertSee('data-home-profile-tab-trigger="prestasi-siswa"', false)
            ->assertSee('Kepala Sekolah')
            ->assertSee('Ibu Kepala')
            ->assertSee('Nama Sekolah')
            ->assertSee('SMA AFBS')
            ->assertSee('NPSN')
            ->assertSee('Tanggal Akreditasi Turun')
            ->assertSee('01/12/2024')
            ->assertSee('Tanggal Berdiri Sekolah')
            ->assertSee('17/07/2001')
            ->assertSee('Lihat Dokumen Akreditasi')
            ->assertSee('Prestasi Siswa/i')
            ->assertSee('Pencarian data siswa aktif dan alumni')
            ->assertSee('Santri Aktif')
            ->assertSee('1234567890')
            ->assertSee('03/02/2010')
            ->assertSee('Agenda kegiatan yang dijadwalkan')
            ->assertSee('home-agenda-calendar')
            ->assertSee('Perkembangan kegiatan terbaru')
            ->assertSee('Kegiatan Wisata Santri')
            ->assertSee('Sedang briefing keberangkatan.')
            ->assertSee('Laboratorium')
            ->assertSee('Apel pagi')
            ->assertSee('Nasi, ayam, sayur')
            ->assertSee('Siswa Aktif (L/P)')
            ->assertSee('Guru (L/P)')
            ->assertSee('Tendik (L/P)')
            ->assertSee('Rombel')
            ->assertSee('Proker')
            ->assertSee('1 / 1')
            ->assertSee('1 / 1')
            ->assertSee('X-A')
            ->assertSee('X-B')
            ->assertSee('Bidang: 2')
            ->assertSee('Selesai')
            ->assertSee('Berjalan')
            ->assertSee('Draft')
            ->assertSee(route('news.show', $berita), false);

        $html = $response->getContent();

        $this->assertStringContainsString('data-home-mini-charts="overview"', $html);
        $this->assertStringContainsString('data-home-mini-chart-card="student-active-gender"', $html);
        $this->assertStringContainsString('data-home-mini-chart-card="guru-gender"', $html);
        $this->assertStringContainsString('data-home-mini-chart-card="tendik-gender"', $html);
        $this->assertStringContainsString('data-home-mini-chart-card="rombel"', $html);
        $this->assertStringContainsString('data-home-mini-chart-card="proker-status"', $html);
        $this->assertStringContainsString('data-home-mini-chart-visual="rombel-bars"', $html);
        $this->assertStringContainsString('data-home-mini-chart-visual="donut-segments"', $html);
        $this->assertMatchesRegularExpression('/id="home-profile-panel-prestasi-siswa"[^>]*data-home-profile-tab-panel="prestasi-siswa"[^>]*hidden/s', $html);
        $this->assertStringContainsString('--mini-chart-fill: 50%;', $html);
        $this->assertTrue(strpos($html, 'data-home-profile-tabs') < strpos($html, 'data-home-mini-charts="overview"'));
        $this->assertStringNotContainsString('Jumlah siswa yang saat ini masih aktif terdata.', $html);
        $this->assertStringNotContainsString('Data lulusan dan riwayat alumni yang telah tercatat.', $html);
        $this->assertStringNotContainsString('Informasi kegiatan yang sedang dipublikasikan beserta perkembangannya.', $html);
    }

    public function test_home_landing_page_does_not_preload_student_results_when_query_is_empty(): void
    {
        DataSiswa::query()->create([
            'nama' => 'Abdullah Karim',
            'nisn' => '1122334455',
            'status' => 'aktif',
            'tanggal_lahir' => '2010-02-03',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Silakan ketik nama atau NISN siswa terlebih dahulu.')
            ->assertDontSee('Abdullah Karim');
    }

    public function test_home_landing_page_student_search_matches_partial_keyword(): void
    {
        DataSiswa::query()->create([
            'nama' => 'Abdullah Karim',
            'nisn' => '1122334455',
            'status' => 'aktif',
            'tanggal_lahir' => '2010-02-03',
        ]);

        DataSiswa::query()->create([
            'nama' => 'Karina Putri',
            'nisn' => '2233445566',
            'status' => 'aktif',
            'tanggal_lahir' => '2010-04-11',
        ]);

        $this->get(route('home', ['q' => 'abd']))
            ->assertOk()
            ->assertSee('Abdullah Karim')
            ->assertDontSee('Karina Putri');
    }

    public function test_home_landing_page_degrades_safely_when_data_siswa_table_is_missing(): void
    {
        if (Schema::hasTable('data_siswa')) {
            Schema::drop('data_siswa');
        }

        if (Schema::hasTable('guru_tendik')) {
            Schema::drop('guru_tendik');
        }

        if (Schema::hasTable('prokers')) {
            Schema::drop('prokers');
        }

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('Pencarian data siswa aktif dan alumni')
            ->assertSee('Silakan ketik nama atau NISN siswa terlebih dahulu.')
            ->assertSee('Siswa Aktif (L/P)')
            ->assertSee('Guru (L/P)')
            ->assertSee('Tendik (L/P)')
            ->assertSee('Rombel')
            ->assertSee('Proker')
            ->assertSee('0 / 0');

        $html = $response->getContent();
        $this->assertSame(5, substr_count($html, 'data-home-mini-chart-card='));
        $this->assertSame(3, substr_count($html, '--mini-chart-fill: 0%;'));
        $this->assertStringContainsString('Data rombel aktif belum tersedia.', $html);
    }

    public function test_home_landing_page_degrades_safely_when_tracker_columns_are_missing(): void
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

        Berita::query()->create([
            'judul' => 'Informasi tanpa tracker',
            'konten' => 'Tetap tampil sebagai berita aktif.',
            'status' => 'aktif',
            'tanggal_berita' => '2026-04-10',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Perkembangan kegiatan terbaru')
            ->assertSee('Informasi perkembangan kegiatan belum tersedia saat ini.');
    }

    public function test_home_landing_page_uses_latest_timeline_update_when_snapshot_fields_are_empty(): void
    {
        $berita = Berita::query()->create([
            'judul' => 'Pelatihan Dai Muda',
            'konten' => 'Kegiatan utama pembinaan public speaking.',
            'status' => 'aktif',
            'tanggal_berita' => '2026-04-15',
        ]);

        $berita->updates()->create([
            'phase' => 'acara',
            'progress_percent' => 55,
            'tanggal_update' => '2026-04-15 10:00:00',
            'update_text' => 'Peserta sedang tampil bergantian di hadapan pembina.',
            'documentation_media' => ['news/documentation/dai-1.jpg'],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Pelatihan Dai Muda')
            ->assertSee('55%')
            ->assertSee('Peserta sedang tampil bergantian di hadapan pembina.');
    }

    public function test_home_landing_page_renders_child_structure_nodes_in_both_desktop_and_mobile_markup(): void
    {
        $root = StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/kepala.jpg',
            'urutan' => 1,
        ]);

        StrukturOrganisasi::query()->create([
            'parent_id' => $root->id,
            'jabatan' => 'Wakil Kurikulum',
            'nama' => 'Ibu Kurikulum',
            'foto' => 'struktur-organisasi/kurikulum.jpg',
            'urutan' => 1,
        ]);

        StrukturOrganisasi::query()->create([
            'parent_id' => $root->id,
            'jabatan' => 'Wakil Kesiswaan',
            'nama' => 'Bapak Kesiswaan',
            'foto' => 'struktur-organisasi/kesiswaan.jpg',
            'urutan' => 2,
        ]);

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('Kepala Sekolah')
            ->assertSee('Wakil Kurikulum')
            ->assertSee('Wakil Kesiswaan');

        $html = $response->getContent();

        $this->assertStringContainsString('class="org-tree__role">Wakil Kurikulum</div>', $html);
        $this->assertStringContainsString('class="org-mobile__role">Wakil Kurikulum</div>', $html);
        $this->assertStringContainsString('class="org-tree__role">Wakil Kesiswaan</div>', $html);
        $this->assertStringContainsString('class="org-mobile__role">Wakil Kesiswaan</div>', $html);
    }

    public function test_home_landing_page_keeps_kepala_sekolah_descendants_visible_when_multiple_roots_exist(): void
    {
        $leadRoot = StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/kepala.jpg',
            'urutan' => 1,
        ]);

        StrukturOrganisasi::query()->create([
            'parent_id' => $leadRoot->id,
            'jabatan' => 'Wakil Kurikulum',
            'nama' => 'Ibu Kurikulum',
            'foto' => 'struktur-organisasi/kurikulum.jpg',
            'urutan' => 1,
        ]);

        StrukturOrganisasi::query()->create([
            'jabatan' => 'Komite Sekolah',
            'nama' => 'Bapak Komite',
            'foto' => 'struktur-organisasi/komite.jpg',
            'kategori' => StrukturOrganisasi::CATEGORY_COMMITTEE,
            'urutan' => 2,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('class="org-tree__role">Wakil Kurikulum</div>', $html);
        $this->assertStringContainsString('class="org-mobile__role">Wakil Kurikulum</div>', $html);
        $this->assertStringContainsString('org-tree-frame', $html);
        $this->assertStringNotContainsString('org-tree-flat', $html);
    }

    public function test_home_landing_page_renders_committee_tab_without_public_profile_link(): void
    {
        $guruTendik = GuruTendik::query()->create([
            'nama' => 'Bapak Komite Asli',
            'jenis_ptk' => 'Guru',
            'jk' => 'L',
            'bio_singkat' => 'Mendampingi penguatan komunikasi antara sekolah dan wali murid.',
        ]);

        StrukturOrganisasi::query()->create([
            'guru_tendik_id' => $guruTendik->id,
            'jabatan' => 'Komite Sekolah',
            'nama' => 'Bapak Komite',
            'foto' => 'struktur-organisasi/komite.jpg',
            'kategori' => StrukturOrganisasi::CATEGORY_COMMITTEE,
            'urutan' => 1,
        ]);

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('data-home-profile-tab-trigger="komite"', false)
            ->assertSee('Komite Sekolah');

        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/id="home-profile-panel-komite"[^>]*data-home-profile-tab-panel="komite"[^>]*hidden/s', $html);
        $this->assertStringNotContainsString(route('guru-tendik.profile', $guruTendik), $html);
    }

    public function test_home_landing_page_shows_latest_committee_period_and_archives_older_periods_without_name_badge(): void
    {
        StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/kepala.jpg',
            'kategori' => StrukturOrganisasi::CATEGORY_SCHOOL,
            'urutan' => 1,
        ]);

        StrukturOrganisasi::query()->create([
            'jabatan' => 'Ketua Komite Lama',
            'nama' => 'Bapak Komite Lama',
            'kategori' => StrukturOrganisasi::CATEGORY_COMMITTEE,
            'periode_tahun' => 2024,
            'periode_label' => '2024-2025',
            'urutan' => 1,
        ]);

        StrukturOrganisasi::query()->create([
            'jabatan' => 'Ketua Komite Aktif',
            'nama' => 'Ibu Komite Aktif',
            'kategori' => StrukturOrganisasi::CATEGORY_COMMITTEE,
            'periode_tahun' => 2026,
            'periode_label' => '2026-2027',
            'urutan' => 1,
        ]);

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('2026-2027')
            ->assertSee('2024-2025')
            ->assertSee('data-committee-period-archive', false)
            ->assertDontSee('Data komite sekolah belum dipublikasikan saat ini.');

        $html = $response->getContent();

        $this->assertStringContainsString('data-committee-period-current="2026-2027"', $html);
        $this->assertStringContainsString('data-committee-period-item="2024-2025"', $html);
        $this->assertStringNotContainsString('data-org-name-badge=', $html);
    }

    public function test_home_landing_page_keeps_photo_modal_trigger_and_public_profile_link_for_linked_nodes(): void
    {
        $guruTendik = GuruTendik::query()->create([
            'nama' => 'Ibu Kepala Asli',
            'jenis_ptk' => 'Guru',
            'jk' => 'P',
            'bio_singkat' => 'Memimpin sekolah dan penguatan mutu layanan.',
        ]);

        StrukturOrganisasi::query()->create([
            'guru_tendik_id' => $guruTendik->id,
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/kepala.jpg',
            'urutan' => 1,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();

        $html = $response->getContent();
        $profileUrl = route('guru-tendik.profile', $guruTendik);

        $this->assertSame(2, substr_count($html, 'data-org-image-name="Ibu Kepala"'));
        $this->assertSame(2, substr_count($html, $profileUrl));
    }

    protected function ensureDataSiswaTable(): void
    {
        if (Schema::hasTable('data_siswa')) {
            return;
        }

        Schema::create('data_siswa', function (Blueprint $table): void {
            $table->id();
            $table->string('nama')->nullable();
            $table->string('nisn')->nullable();
            $table->string('status')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->timestamps();
        });
    }

    protected function ensureDataSiswaHomepageMetricColumns(): void
    {
        if (! Schema::hasTable('data_siswa')) {
            return;
        }

        if (! Schema::hasColumn('data_siswa', 'jk')) {
            Schema::table('data_siswa', function (Blueprint $table): void {
                $table->string('jk', 2)->nullable()->after('status');
            });
        }

        if (! Schema::hasColumn('data_siswa', 'rombel_saat_ini')) {
            Schema::table('data_siswa', function (Blueprint $table): void {
                $table->string('rombel_saat_ini')->nullable()->after('jk');
            });
        }
    }

    protected function ensureBeritaTable(): void
    {
        if (Schema::hasTable('berita')) {
            return;
        }

        Schema::create('berita', function (Blueprint $table): void {
            $table->id();
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
    }

    protected function ensureCalendarEventsTable(): void
    {
        if (Schema::hasTable('calendar_events')) {
            return;
        }

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

    protected function ensureProfilSekolahTable(): void
    {
        if (Schema::hasTable('profil_sekolahs')) {
            Schema::table('profil_sekolahs', function (Blueprint $table): void {
                if (! Schema::hasColumn('profil_sekolahs', 'nama_sekolah')) {
                    $table->string('nama_sekolah', 180)->nullable()->after('title');
                }

                if (! Schema::hasColumn('profil_sekolahs', 'provinsi')) {
                    $table->string('provinsi', 120)->nullable()->after('nama_sekolah');
                }

                if (! Schema::hasColumn('profil_sekolahs', 'desa_kelurahan')) {
                    $table->string('desa_kelurahan', 120)->nullable()->after('provinsi');
                }

                if (! Schema::hasColumn('profil_sekolahs', 'kecamatan')) {
                    $table->string('kecamatan', 120)->nullable()->after('desa_kelurahan');
                }

                if (! Schema::hasColumn('profil_sekolahs', 'kode_pos')) {
                    $table->string('kode_pos', 20)->nullable()->after('alamat');
                }

                if (! Schema::hasColumn('profil_sekolahs', 'website_url')) {
                    $table->string('website_url', 2048)->nullable()->after('kontak_email');
                }

                if (! Schema::hasColumn('profil_sekolahs', 'status_sekolah')) {
                    $table->string('status_sekolah', 120)->nullable()->after('website_url');
                }

                if (! Schema::hasColumn('profil_sekolahs', 'kelompok_sekolah')) {
                    $table->string('kelompok_sekolah', 120)->nullable()->after('status_sekolah');
                }

                if (! Schema::hasColumn('profil_sekolahs', 'terakreditasi')) {
                    $table->string('terakreditasi', 120)->nullable()->after('kelompok_sekolah');
                }

                if (! Schema::hasColumn('profil_sekolahs', 'tanggal_identitas')) {
                    $table->date('tanggal_identitas')->nullable()->after('terakreditasi');
                }

                if (! Schema::hasColumn('profil_sekolahs', 'tahun_berdiri')) {
                    $table->string('tahun_berdiri', 20)->nullable()->after('tanggal_identitas');
                }

                if (! Schema::hasColumn('profil_sekolahs', 'tanggal_berdiri')) {
                    $table->date('tanggal_berdiri')->nullable()->after('tahun_berdiri');
                }

                if (! Schema::hasColumn('profil_sekolahs', 'kbm')) {
                    $table->string('kbm', 120)->nullable()->after('tanggal_berdiri');
                }

                if (! Schema::hasColumn('profil_sekolahs', 'bangunan_sekolah')) {
                    $table->string('bangunan_sekolah', 160)->nullable()->after('kbm');
                }

                if (! Schema::hasColumn('profil_sekolahs', 'luas_bangunan')) {
                    $table->string('luas_bangunan', 120)->nullable()->after('bangunan_sekolah');
                }

                if (! Schema::hasColumn('profil_sekolahs', 'organisasi_penyelenggara')) {
                    $table->string('organisasi_penyelenggara', 180)->nullable()->after('luas_bangunan');
                }

                if (! Schema::hasColumn('profil_sekolahs', 'identitas_tambahan')) {
                    $table->json('identitas_tambahan')->nullable()->after('organisasi_penyelenggara');
                }

                if (! Schema::hasColumn('profil_sekolahs', 'file_akreditasi_path')) {
                    $table->string('file_akreditasi_path')->nullable()->after('identitas_tambahan');
                }
            });

            return;
        }

        Schema::create('profil_sekolahs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('singleton_key')->default(1)->unique();
            $table->string('title', 160)->default('Identitas Sekolah');
            $table->string('nama_sekolah', 180)->nullable();
            $table->string('provinsi', 120)->nullable();
            $table->string('desa_kelurahan', 120)->nullable();
            $table->string('kecamatan', 120)->nullable();
            $table->text('alamat')->nullable();
            $table->string('kode_pos', 20)->nullable();
            $table->string('kontak_telepon', 60)->nullable();
            $table->string('kontak_email', 120)->nullable();
            $table->string('website_url', 2048)->nullable();
            $table->string('status_sekolah', 120)->nullable();
            $table->string('kelompok_sekolah', 120)->nullable();
            $table->string('terakreditasi', 120)->nullable();
            $table->date('tanggal_identitas')->nullable();
            $table->string('tahun_berdiri', 20)->nullable();
            $table->date('tanggal_berdiri')->nullable();
            $table->string('kbm', 120)->nullable();
            $table->string('bangunan_sekolah', 160)->nullable();
            $table->string('luas_bangunan', 120)->nullable();
            $table->string('organisasi_penyelenggara', 180)->nullable();
            $table->json('identitas_tambahan')->nullable();
            $table->string('file_akreditasi_path')->nullable();
            $table->string('maps_url', 2048)->nullable();
            $table->string('youtube_url', 2048)->nullable();
            $table->string('instagram_url', 2048)->nullable();
            $table->string('facebook_url', 2048)->nullable();
            $table->string('tiktok_url', 2048)->nullable();
            $table->json('fasilitas')->nullable();
            $table->json('jadwal_kbm')->nullable();
            $table->json('menu_makan')->nullable();
            $table->timestamps();
        });
    }

    protected function ensureStrukturOrganisasiTable(): void
    {
        Schema::dropIfExists('struktur_organisasis');
        Schema::dropIfExists('guru_tendik');

        Schema::create('guru_tendik', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->string('jenis_ptk')->nullable();
            $table->string('jk', 2)->nullable();
            $table->string('foto_profil')->nullable();
            $table->text('bio_singkat')->nullable();
            $table->timestamps();
        });

        Schema::create('struktur_organisasis', function (Blueprint $table): void {
            $table->id();
            $table->string('jabatan', 150);
            $table->string('nama', 150);
            $table->string('foto')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('homepage_parent_id')->nullable();
            $table->unsignedBigInteger('guru_tendik_id')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->unsignedInteger('homepage_row')->nullable();
            $table->unsignedInteger('homepage_order')->nullable();
            $table->string('kategori', 20)->default(StrukturOrganisasi::CATEGORY_SCHOOL);
            $table->unsignedSmallInteger('periode_tahun')->nullable();
            $table->string('periode_label', 100)->nullable();
            $table->timestamps();
        });
    }

    protected function ensureProkersTable(): void
    {
        if (Schema::hasTable('prokers')) {
            return;
        }

        Schema::create('prokers', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
    }

    protected function ensureProkerBidangsTable(): void
    {
        if (Schema::hasTable('proker_bidangs')) {
            return;
        }

        Schema::create('proker_bidangs', function (Blueprint $table): void {
            $table->id();
            $table->string('nama')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    protected function ensureProkerMetricColumns(): void
    {
        if (! Schema::hasTable('prokers')) {
            return;
        }

        if (! Schema::hasColumn('prokers', 'status')) {
            Schema::table('prokers', function (Blueprint $table): void {
                $table->string('status')->nullable()->after('id');
            });
        }

        if (! Schema::hasColumn('prokers', 'bidang_id')) {
            Schema::table('prokers', function (Blueprint $table): void {
                $table->unsignedBigInteger('bidang_id')->nullable()->after('status');
            });
        }
    }
}








