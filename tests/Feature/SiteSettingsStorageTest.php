<?php

namespace Tests\Feature;

use App\Contracts\SiteSettingsAccessor;
use App\Models\Pengaturan;
use App\Support\SiteSettings\SiteSettingKeys;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SiteSettingsStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->recreatePengaturanTable();
    }

    public function test_stored_settings_values_and_asset_paths_are_resolved_and_normalized(): void
    {
        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::SITE_NAME,
            'nilai_pengaturan' => 'Portal AFBS',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::TOPBAR_TEXT,
            'nilai_pengaturan' => 'Informasi terverifikasi untuk wali dan santri.',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::LOGO_PATH,
            'nilai_pengaturan' => 'public/site/logo.png',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::FAVICON_PATH,
            'nilai_pengaturan' => 'storage/site/favicon.ico',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::DEFAULT_OG_IMAGE,
            'nilai_pengaturan' => 'https://cdn.example.test/og/cover.jpg',
        ]);

        /** @var SiteSettingsAccessor $settings */
        $settings = app(SiteSettingsAccessor::class);

        $this->assertSame('Portal AFBS', $settings->siteName());
        $this->assertSame('Informasi terverifikasi untuk wali dan santri.', $settings->topbarText());
        $this->assertSame('/storage/site/logo.png', $settings->logoPath());
        $this->assertSame('/storage/site/favicon.ico', $settings->faviconPath());
        $this->assertSame('https://cdn.example.test/og/cover.jpg', $settings->defaultOgImage());
        $this->assertSame('SMA AFBS', $settings->defaultSeoTitle());
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
}
