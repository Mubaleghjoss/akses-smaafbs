<?php

namespace Tests\Feature;

use App\Models\Pengaturan;
use App\Support\SiteSettings\SiteSettingKeys;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicLayoutBrandingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->recreatePengaturanTable();
        $this->recreateBeritaTable();
    }

    public function test_public_layout_reads_branding_and_asset_links_from_site_settings(): void
    {
        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::SITE_NAME,
            'nilai_pengaturan' => 'Portal Sekolah AFBS',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::TOPBAR_BADGE,
            'nilai_pengaturan' => 'Sekolah Berbasis Asrama',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::TOPBAR_TEXT,
            'nilai_pengaturan' => 'Informasi resmi untuk wali dan santri.',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::FOOTER_PRIMARY_TEXT,
            'nilai_pengaturan' => 'Portal Sekolah AFBS',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::FOOTER_SECONDARY_TEXT,
            'nilai_pengaturan' => 'Konten dipublikasikan sebagai layanan informasi sekolah.',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::LOGO_PATH,
            'nilai_pengaturan' => 'site-branding/logo/logo-public.png',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::FAVICON_PATH,
            'nilai_pengaturan' => 'site-branding/favicon/favicon-public.png',
        ]);

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('Portal Sekolah AFBS')
            ->assertSee('Sekolah Berbasis Asrama')
            ->assertSee('Informasi resmi untuk wali dan santri.')
            ->assertSee('Konten dipublikasikan sebagai layanan informasi sekolah.')
            ->assertSee('/storage/site-branding/logo/logo-public.png')
            ->assertSee('<link rel="manifest" href="http://localhost/manifest.webmanifest">', false)
            ->assertSee('/storage/site-branding/favicon/favicon-public.png')
            ->assertSee('<meta property="og:site_name" content="Portal Sekolah AFBS">', false)
            ->assertSee('<meta property="og:image" content="/storage/site-branding/logo/logo-public.png">', false);

        $response->assertDontSee('Portal informasi dan layanan sekolah');
    }

    protected function recreatePengaturanTable(): void
    {
        Schema::dropIfExists('pengaturan');

        Schema::create('pengaturan', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_pengaturan')->unique();
            $table->text('nilai_pengaturan')->nullable();
        });
    }

    protected function recreateBeritaTable(): void
    {
        Schema::dropIfExists('berita');

        Schema::create('berita', function (Blueprint $table): void {
            $table->id();
            $table->string('judul')->nullable();
            $table->text('konten')->nullable();
            $table->string('gambar')->nullable();
            $table->string('status')->nullable();
            $table->date('tanggal_berita')->nullable();
            $table->timestamps();
        });
    }
}
