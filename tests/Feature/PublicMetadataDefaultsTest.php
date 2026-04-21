<?php

namespace Tests\Feature;

use App\Models\Berita;
use App\Models\Pengaturan;
use App\Support\SiteSettings\SiteSettingKeys;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicMetadataDefaultsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->recreatePengaturanTable();
        $this->recreateBeritaTable();
    }

    public function test_homepage_uses_site_settings_for_default_meta_tags(): void
    {
        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::SITE_NAME,
            'nilai_pengaturan' => 'Portal AFBS Publik',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::DEFAULT_SEO_DESCRIPTION,
            'nilai_pengaturan' => 'Default deskripsi portal sekolah.',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::DEFAULT_OG_TITLE,
            'nilai_pengaturan' => 'Default OG Judul Sekolah',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::DEFAULT_OG_DESCRIPTION,
            'nilai_pengaturan' => 'Default OG deskripsi sekolah.',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::DEFAULT_OG_IMAGE,
            'nilai_pengaturan' => 'https://cdn.example.test/meta/default-og.png',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::THEME_COLOR,
            'nilai_pengaturan' => '#123456',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<meta name="description" content="Default deskripsi portal sekolah.">', false)
            ->assertSee('<meta property="og:site_name" content="Portal AFBS Publik">', false)
            ->assertSee('<meta property="og:title" content="Default OG Judul Sekolah">', false)
            ->assertSee('<meta property="og:description" content="Default OG deskripsi sekolah.">', false)
            ->assertSee('<meta property="og:image" content="https://cdn.example.test/meta/default-og.png">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('<meta name="theme-color" content="#123456">', false);
    }

    public function test_homepage_uses_logo_then_favicon_for_og_image_when_default_og_image_is_missing(): void
    {
        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::LOGO_PATH,
            'nilai_pengaturan' => 'site-branding/logo/logo-og-fallback.png',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::FAVICON_PATH,
            'nilai_pengaturan' => 'site-branding/favicon/favicon-og-fallback.png',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<meta property="og:image" content="/storage/site-branding/logo/logo-og-fallback.png">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);

        Pengaturan::query()
            ->where('nama_pengaturan', SiteSettingKeys::LOGO_PATH)
            ->update(['nilai_pengaturan' => '']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<meta property="og:image" content="/storage/site-branding/favicon/favicon-og-fallback.png">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    }

    public function test_news_detail_overrides_default_meta_with_richer_page_data(): void
    {
        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::DEFAULT_OG_TITLE,
            'nilai_pengaturan' => 'Default OG Judul Sekolah',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::DEFAULT_OG_DESCRIPTION,
            'nilai_pengaturan' => 'Default OG deskripsi sekolah.',
        ]);

        $news = Berita::query()->create([
            'judul' => 'Kegiatan Kemah Santri',
            'konten' => 'Ringkasan kegiatan kemah santri untuk melatih kemandirian dan kebersamaan.',
            'gambar' => 'kemah.jpg',
            'status' => 'aktif',
            'tanggal_berita' => '2026-03-31',
        ]);

        $response = $this->get(route('news.show', $news));

        $response
            ->assertOk()
            ->assertSee('<meta property="og:title" content="Kegiatan Kemah Santri">', false)
            ->assertSee('<meta property="og:description" content="Ringkasan kegiatan kemah santri untuk melatih kemandirian dan kebersamaan.">', false)
            ->assertSee('<meta property="og:image" content="/storage/news/kemah.jpg">', false)
            ->assertSee('<meta name="twitter:title" content="Kegiatan Kemah Santri">', false)
            ->assertSee('<meta name="twitter:description" content="Ringkasan kegiatan kemah santri untuk melatih kemandirian dan kebersamaan.">', false)
            ->assertSee('<meta name="twitter:image" content="/storage/news/kemah.jpg">', false);

        $response
            ->assertDontSee('<meta property="og:title" content="Default OG Judul Sekolah">', false)
            ->assertDontSee('<meta property="og:description" content="Default OG deskripsi sekolah.">', false);
    }

    public function test_manifest_endpoint_returns_typed_settings_and_icon_fallbacks(): void
    {
        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::PWA_APP_NAME,
            'nilai_pengaturan' => 'Portal SMA AFBS',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::PWA_SHORT_NAME,
            'nilai_pengaturan' => 'AFBS',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::THEME_COLOR,
            'nilai_pengaturan' => '#2255aa',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::FAVICON_PATH,
            'nilai_pengaturan' => 'site-branding/favicon/favicon-manifest.png',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::LOGO_PATH,
            'nilai_pengaturan' => 'site-branding/logo/logo-manifest.png',
        ]);

        $response = $this->get('/manifest.webmanifest');

        $response->assertOk();
        $this->assertStringContainsString('application/manifest+json', (string) $response->headers->get('content-type'));

        $payload = $response->json();

        $this->assertSame('Portal SMA AFBS', $payload['name']);
        $this->assertSame('AFBS', $payload['short_name']);
        $this->assertSame('#2255aa', $payload['theme_color']);
        $this->assertSame('/storage/site-branding/favicon/favicon-manifest.png', $payload['icons'][0]['src']);

        Pengaturan::query()
            ->where('nama_pengaturan', SiteSettingKeys::FAVICON_PATH)
            ->update(['nilai_pengaturan' => '']);

        $responseWithoutFavicon = $this->get('/manifest.webmanifest');
        $payloadWithoutFavicon = $responseWithoutFavicon->json();
        $this->assertSame('/storage/site-branding/logo/logo-manifest.png', $payloadWithoutFavicon['icons'][0]['src']);

        Pengaturan::query()
            ->where('nama_pengaturan', SiteSettingKeys::LOGO_PATH)
            ->update(['nilai_pengaturan' => '']);

        $responseWithoutBrandingIcons = $this->get('/manifest.webmanifest');
        $payloadWithoutBrandingIcons = $responseWithoutBrandingIcons->json();
        $this->assertSame('http://localhost/favicon.ico', $payloadWithoutBrandingIcons['icons'][0]['src']);
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
