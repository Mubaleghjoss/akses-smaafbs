<?php

namespace Tests\Feature;

use App\Models\GuruTendik;
use App\Models\StrukturOrganisasi;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\BootstrapsStudentAndTeacherTables;
use Tests\TestCase;

class GuruTendikPublicProfileTest extends TestCase
{
    use BootstrapsStudentAndTeacherTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapStudentAndTeacherTables();
        $this->ensureStrukturOrganisasiTable();
    }

    public function test_public_profile_page_renders_simple_biography_and_active_duties(): void
    {
        $guruTendik = GuruTendik::query()->create([
            'nama' => 'Ustadz Ahmad',
            'jenis_ptk' => 'Guru',
            'jk' => 'L',
            'foto_profil' => 'guru-tendik/profil/ahmad.jpg',
            'bio_singkat' => 'Membina akademik dan karakter santri.',
        ]);

        $guruTendik->tugasTambahan()->create([
            'tugas_tambahan' => 'Koordinator Tahfiz',
            'no_sk' => 'SK-001',
            'tmt' => now()->subMonth()->toDateString(),
            'keterangan' => 'Mengawal program tahfiz harian.',
        ]);

        $guruTendik->tugasTambahan()->create([
            'tugas_tambahan' => 'Panitia Lama',
            'no_sk' => 'SK-OLD',
            'tmt' => now()->subYear()->toDateString(),
            'tst' => now()->subMonths(6)->toDateString(),
        ]);

        StrukturOrganisasi::query()->create([
            'guru_tendik_id' => $guruTendik->id,
            'jabatan' => 'Wakil Kurikulum',
            'nama' => 'Ustadz Ahmad',
            'foto' => 'struktur-organisasi/kurikulum.jpg',
            'urutan' => 1,
        ]);

        $this->get(route('guru-tendik.profile', $guruTendik))
            ->assertOk()
            ->assertSee('Ustadz Ahmad')
            ->assertSee('Wakil Kurikulum')
            ->assertSee('Membina akademik dan karakter santri.')
            ->assertSee('Koordinator Tahfiz')
            ->assertDontSee('Panitia Lama');
    }

    public function test_public_profile_page_returns_not_found_for_unlinked_guru_tendik(): void
    {
        $guruTendik = GuruTendik::query()->create([
            'nama' => 'Ustadzah Belum Link',
            'jenis_ptk' => 'Guru',
            'jk' => 'P',
        ]);

        $this->get(route('guru-tendik.profile', $guruTendik))
            ->assertNotFound();
    }

    protected function ensureStrukturOrganisasiTable(): void
    {
        if (Schema::hasTable('struktur_organisasis')) {
            Schema::table('struktur_organisasis', function (Blueprint $table): void {
                if (! Schema::hasColumn('struktur_organisasis', 'guru_tendik_id')) {
                    $table->unsignedBigInteger('guru_tendik_id')->nullable();
                }

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
            });

            return;
        }

        Schema::create('struktur_organisasis', function (Blueprint $table): void {
            $table->id();
            $table->string('jabatan', 150);
            $table->string('nama', 150);
            $table->string('foto')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('guru_tendik_id')->nullable();
            $table->unsignedBigInteger('homepage_parent_id')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->unsignedInteger('homepage_row')->nullable();
            $table->unsignedInteger('homepage_order')->nullable();
            $table->string('kategori', 20)->default(StrukturOrganisasi::CATEGORY_SCHOOL);
            // Kolom periode komite (migrasi 2026_04_05_110000). Disertakan agar
            // bentuk tabel konsisten dengan test lain yang memakai tabel yang
            // sama — perbedaan bentuk menimbulkan kegagalan bergantung urutan.
            $table->unsignedSmallInteger('periode_tahun')->nullable();
            $table->string('periode_label', 100)->nullable();
            $table->timestamps();
        });
    }
}
