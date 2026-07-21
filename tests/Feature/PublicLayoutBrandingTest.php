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
            ->assertSee('<link rel="manifest" href="'.url('/manifest.webmanifest').'">', false)
            ->assertSee('/storage/site-branding/favicon/favicon-public.png')
            ->assertSee('<meta property="og:site_name" content="Portal Sekolah AFBS">', false)
            ->assertSee('<meta property="og:image" content="/storage/site-branding/logo/logo-public.png">', false);

        $response->assertDontSee('Portal informasi dan layanan sekolah');
    }

    public function test_public_layout_has_accessible_mobile_navigation_drawer(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('id="public-navigation"', false)
            ->assertSee('id="public-mobile-menu-toggle"', false)
            ->assertSee('aria-controls="public-mobile-menu"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('id="public-mobile-menu-overlay"', false)
            ->assertSee('id="public-mobile-menu"', false)
            ->assertSee('aria-hidden="true"', false)
            ->assertSee('inert', false)
            ->assertSee('Menu Utama')
            ->assertSee('Beranda')
            ->assertSee('Literasi Numerasi')
            ->assertSee('Akses Perpus')
            ->assertSee('Login Admin')
            ->assertSee('Install Aplikasi');

        $this->assertMatchesRegularExpression(
            '/public-mobile-menu-link is-active[^>]*aria-current="page"[^>]*>\s*<span[^>]*>.*?<\/span>\s*<span>Beranda<\/span>/s',
            $response->getContent(),
        );
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
