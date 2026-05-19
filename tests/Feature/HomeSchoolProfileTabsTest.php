<?php

namespace Tests\Feature;

use App\Models\StrukturOrganisasi;
use App\Models\DataSiswa;
use App\Models\Prestasi;
use App\Models\ProfilSekolah;
use App\Models\VisiMisi;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomeSchoolProfileTabsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureBeritaTable();
        $this->ensureDataSiswaTable();
        $this->ensureStrukturOrganisasiTable();
        $this->ensureVisiMisiTable();
        $this->ensureProfilSekolahTable();
        $this->ensurePrestasiTable();
        $this->ensurePrestasiHistoriesTable();
    }

    public function test_home_school_profile_uses_shared_tabs_with_struktur_as_default_panel(): void
    {
        StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/kepala.jpg',
            'kategori' => StrukturOrganisasi::CATEGORY_SCHOOL,
            'urutan' => 1,
        ]);

        StrukturOrganisasi::query()->create([
            'jabatan' => 'Ketua Komite',
            'nama' => 'Bapak Komite',
            'foto' => 'struktur-komite/ketua.jpg',
            'kategori' => StrukturOrganisasi::CATEGORY_COMMITTEE,
            'urutan' => 1,
        ]);

        VisiMisi::query()->create([
            'title' => 'Visi dan Misi SMA AFBS',
            'content' => '<p>Arah utama sekolah untuk pembinaan karakter dan akademik.</p>',
        ]);

        ProfilSekolah::query()->create([
            'title' => 'Identitas Sekolah',
            'nama_sekolah' => 'SMA AFBS',
            'provinsi' => 'Jawa Barat',
            'alamat' => 'Jl. Sekolah No. 1',
            'tanggal_identitas' => '2024-12-01',
            'tanggal_berdiri' => '2001-07-17',
            'file_akreditasi_path' => 'identitas-sekolah/akreditasi/sk-akreditasi.pdf',
            'website_url' => 'https://afbs.test',
            'maps_url' => 'https://maps.google.com/?q=sekolah',
            'identitas_tambahan' => [
                ['label' => 'NPSN', 'value' => '20260001'],
            ],
            'fasilitas' => [
                ['nama' => 'Perpustakaan', 'foto' => 'profil-sekolah/fasilitas/perpus.jpg', 'keterangan' => 'Ruang baca nyaman.'],
            ],
            'jadwal_kbm' => [
                ['waktu' => '07.00 - 07.30', 'kegiatan' => 'Apel pagi'],
            ],
            'menu_makan' => [
                ['hari' => 'Senin', 'menu' => 'Nasi, ayam, sayur'],
            ],
        ]);

        $siswa = DataSiswa::query()->create([
            'nama' => 'Anisa Prestasi',
            'rombel_saat_ini' => 'XI IPA 1',
            'status' => 'aktif',
        ]);

        Prestasi::query()->create([
            'siswa_id' => $siswa->id,
            'nama_lomba' => 'Olimpiade Sains Nasional',
            'kategori' => Prestasi::CATEGORY_AKADEMIK,
            'tanggal_prestasi' => '2026-03-10',
            'penyelenggara' => 'Pusat Prestasi Nasional',
            'juara' => 'Juara 1',
            'hadiah' => 'Medali emas',
            'keterangan' => 'Prestasi tingkat nasional bidang sains.',
        ]);

        Prestasi::query()->create([
            'siswa_id' => $siswa->id,
            'nama_lomba' => 'Kejuaraan Pencak Silat',
            'kategori' => Prestasi::CATEGORY_NON_AKADEMIK,
            'tanggal_prestasi' => '2026-03-12',
            'penyelenggara' => 'Ikatan Pencak Silat',
            'juara' => 'Juara 2',
            'hadiah' => 'Piala',
            'keterangan' => 'Prestasi tingkat provinsi bidang olahraga.',
        ]);

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('data-home-profile-tabs', false)
            ->assertSee('data-home-profile-tab-trigger="struktur"', false)
            ->assertSee('data-home-profile-tab-trigger="komite"', false)
            ->assertSee('data-home-profile-tab-trigger="identitas-sekolah"', false)
            ->assertSee('data-home-profile-tab-trigger="prestasi-siswa"', false)
            ->assertSee('data-home-profile-tab-trigger="visi-misi"', false)
            ->assertSee('Struktur Sekolah')
            ->assertSee('Struktur Komite')
            ->assertSee('Identitas Sekolah')
            ->assertSee('Prestasi Siswa/i')
            ->assertSee('Nama Sekolah')
            ->assertSee('SMA AFBS')
            ->assertSee('NPSN')
            ->assertSee('20260001')
            ->assertSee('Tanggal Akreditasi Turun')
            ->assertSee('01/12/2024')
            ->assertSee('Tanggal Berdiri Sekolah')
            ->assertSee('17/07/2001')
            ->assertSee('Lihat Dokumen Akreditasi')
            ->assertSee('Kepala Sekolah')
            ->assertSee('Ketua Komite')
            ->assertSee('Perpustakaan')
            ->assertSee('Olimpiade Sains Nasional')
            ->assertSee('Akademik')
            ->assertSee('Kejuaraan Pencak Silat')
            ->assertSee('Non Akademik')
            ->assertSee('Akademik: 1')
            ->assertSee('Non Akademik: 1')
            ->assertSee('Anisa Prestasi')
            ->assertSee('Juara 1')
            ->assertSee('Visi dan Misi SMA AFBS')
            ->assertSee('Arah utama sekolah untuk pembinaan karakter dan akademik.', false)
            ->assertDontSee('Data komite sekolah belum dipublikasikan saat ini.');

        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/id="home-profile-tab-struktur"[^>]*aria-selected="true"/s', $html);
        $this->assertMatchesRegularExpression('/id="home-profile-panel-komite"[^>]*data-home-profile-tab-panel="komite"[^>]*hidden/s', $html);
        $this->assertMatchesRegularExpression('/id="home-profile-panel-identitas-sekolah"[^>]*data-home-profile-tab-panel="identitas-sekolah"[^>]*hidden/s', $html);
        $this->assertMatchesRegularExpression('/id="home-profile-panel-prestasi-siswa"[^>]*data-home-profile-tab-panel="prestasi-siswa"[^>]*hidden/s', $html);
        $this->assertMatchesRegularExpression('/id="home-profile-panel-visi-misi"[^>]*data-home-profile-tab-panel="visi-misi"[^>]*hidden/s', $html);
        $this->assertStringContainsString('data-achievement-filter-root', $html);
        $this->assertStringContainsString('data-achievement-filter-trigger="'.Prestasi::CATEGORY_AKADEMIK.'"', $html);
        $this->assertStringContainsString('data-achievement-filter-trigger="'.Prestasi::CATEGORY_NON_AKADEMIK.'"', $html);
        $this->assertStringContainsString('data-achievement-category="'.Prestasi::CATEGORY_AKADEMIK.'"', $html);
        $this->assertStringContainsString('data-achievement-category="'.Prestasi::CATEGORY_NON_AKADEMIK.'"', $html);
    }

    public function test_home_school_profile_shows_visi_misi_fallback_when_record_is_missing(): void
    {
        VisiMisi::query()->delete();
        StrukturOrganisasi::query()->delete();

        StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/kepala.jpg',
            'kategori' => StrukturOrganisasi::CATEGORY_SCHOOL,
            'urutan' => 1,
        ]);

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('Konten visi misi sekolah belum dipublikasikan saat ini.')
            ->assertSee('data-home-profile-tab-trigger="struktur"', false)
            ->assertSee('data-home-profile-tab-trigger="komite"', false)
            ->assertSee('data-home-profile-tab-trigger="identitas-sekolah"', false)
            ->assertSee('data-home-profile-tab-trigger="prestasi-siswa"', false)
            ->assertSee('data-home-profile-tab-trigger="visi-misi"', false);

        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/id="home-profile-tab-struktur"[^>]*aria-selected="true"/s', $html);
        $this->assertMatchesRegularExpression('/id="home-profile-panel-komite"[^>]*data-home-profile-tab-panel="komite"[^>]*hidden/s', $html);
        $this->assertMatchesRegularExpression('/id="home-profile-panel-identitas-sekolah"[^>]*data-home-profile-tab-panel="identitas-sekolah"[^>]*hidden/s', $html);
        $this->assertMatchesRegularExpression('/id="home-profile-panel-prestasi-siswa"[^>]*data-home-profile-tab-panel="prestasi-siswa"[^>]*hidden/s', $html);
        $this->assertMatchesRegularExpression('/id="home-profile-panel-visi-misi"[^>]*data-home-profile-tab-panel="visi-misi"[^>]*hidden/s', $html);
    }

    protected function ensureDataSiswaTable(): void
    {
        if (Schema::hasTable('data_siswa')) {
            Schema::table('data_siswa', function (Blueprint $table): void {
                if (! Schema::hasColumn('data_siswa', 'rombel_saat_ini')) {
                    $table->string('rombel_saat_ini')->nullable();
                }

                if (! Schema::hasColumn('data_siswa', 'status')) {
                    $table->string('status')->nullable();
                }

                if (! Schema::hasColumn('data_siswa', 'kepribadian')) {
                    $table->string('kepribadian', 100)->nullable();
                }

                if (! Schema::hasColumn('data_siswa', 'gaya_belajar')) {
                    $table->string('gaya_belajar', 100)->nullable();
                }

                if (! Schema::hasColumn('data_siswa', 'profiling')) {
                    $table->string('profiling', 150)->nullable();
                }

                if (! Schema::hasColumn('data_siswa', 'mbti')) {
                    $table->string('mbti', 20)->nullable();
                }
            });

            return;
        }

        Schema::create('data_siswa', function (Blueprint $table): void {
            $table->id();
            $table->string('nama')->nullable();
            $table->string('rombel_saat_ini')->nullable();
            $table->string('status')->nullable();
            $table->string('kepribadian', 100)->nullable();
            $table->string('gaya_belajar', 100)->nullable();
            $table->string('profiling', 150)->nullable();
            $table->string('mbti', 20)->nullable();
            $table->timestamps();
        });
    }

    protected function ensurePrestasiTable(): void
    {
        if (Schema::hasTable('prestasis')) {
            Schema::table('prestasis', function (Blueprint $table): void {
                if (! Schema::hasColumn('prestasis', 'kategori')) {
                    $table->string('kategori', 30)->nullable();
                }
            });

            return;
        }

        Schema::create('prestasis', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('siswa_id')->nullable();
            $table->string('nama_lomba')->nullable();
            $table->string('kategori', 30)->nullable();
            $table->date('tanggal_prestasi')->nullable();
            $table->string('penyelenggara')->nullable();
            $table->string('juara')->nullable();
            $table->string('hadiah')->nullable();
            $table->text('keterangan')->nullable();
            $table->json('dokumentasi')->nullable();
            $table->json('sertifikat_files')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('gdrive_file_url')->nullable();
            $table->string('gdrive_folder_url')->nullable();
            $table->timestamps();
        });
    }

    protected function ensurePrestasiHistoriesTable(): void
    {
        if (Schema::hasTable('prestasi_histories')) {
            return;
        }

        Schema::create('prestasi_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('prestasi_id')->nullable();
            $table->string('aksi')->nullable();
            $table->string('judul_ringkas')->nullable();
            $table->json('snapshot')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
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
            $table->timestamps();
        });
    }

    protected function ensureStrukturOrganisasiTable(): void
    {
        if (Schema::hasTable('struktur_organisasis')) {
            Schema::table('struktur_organisasis', function (Blueprint $table): void {
                if (! Schema::hasColumn('struktur_organisasis', 'homepage_parent_id')) {
                    $table->unsignedBigInteger('homepage_parent_id')->nullable();
                }

                if (! Schema::hasColumn('struktur_organisasis', 'homepage_row')) {
                    $table->unsignedInteger('homepage_row')->nullable();
                }

                if (! Schema::hasColumn('struktur_organisasis', 'homepage_order')) {
                    $table->unsignedInteger('homepage_order')->nullable();
                }

                if (! Schema::hasColumn('struktur_organisasis', 'kategori')) {
                    $table->string('kategori', 20)->default(StrukturOrganisasi::CATEGORY_SCHOOL);
                }

                if (! Schema::hasColumn('struktur_organisasis', 'periode_tahun')) {
                    $table->unsignedSmallInteger('periode_tahun')->nullable();
                }

                if (! Schema::hasColumn('struktur_organisasis', 'periode_label')) {
                    $table->string('periode_label', 100)->nullable();
                }
            });

            return;
        }

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

    protected function ensureVisiMisiTable(): void
    {
        if (Schema::hasTable('visi_misis')) {
            return;
        }

        Schema::create('visi_misis', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('singleton_key')->default(1)->unique();
            $table->string('title', 160);
            $table->longText('content');
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
}







