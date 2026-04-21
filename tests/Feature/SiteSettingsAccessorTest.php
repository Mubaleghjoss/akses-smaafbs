<?php

namespace Tests\Feature;

use App\Contracts\SiteSettingsAccessor;
use App\Support\SiteSettings\SiteSettingKeys;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SiteSettingsAccessorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->recreatePengaturanTable();
    }

    public function test_known_site_settings_resolve_to_defaults_when_not_stored(): void
    {
        /** @var SiteSettingsAccessor $settings */
        $settings = app(SiteSettingsAccessor::class);

        $this->assertSame('SMA AFBS', $settings->siteName());
        $this->assertSame('Sekolah Islam Berasrama', $settings->topbarBadge());
        $this->assertSame('Portal resmi informasi SMA AFBS.', $settings->topbarText());
        $this->assertSame('SMA AFBS', $settings->defaultSeoTitle());
        $this->assertSame('Website resmi SMA AFBS.', $settings->defaultSeoDescription());
        $this->assertSame('#16a34a', $settings->themeColor());
        $this->assertSame('SMA AFBS', $settings->pwaAppName());
        $this->assertSame('AFBS', $settings->pwaShortName());
        $this->assertNull($settings->logoPath());
        $this->assertNull($settings->faviconPath());
        $this->assertNull($settings->defaultOgImage());

        $all = $settings->all();

        $this->assertArrayHasKey(SiteSettingKeys::SITE_NAME, $all);
        $this->assertArrayHasKey(SiteSettingKeys::DEFAULT_OG_IMAGE, $all);
        $this->assertSame('SMA AFBS', $all[SiteSettingKeys::SITE_NAME]);
        $this->assertNull($all[SiteSettingKeys::DEFAULT_OG_IMAGE]);
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
