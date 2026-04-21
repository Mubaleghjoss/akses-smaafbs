<?php

namespace Tests\Feature;

use App\Contracts\SiteSettingsAccessor;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SiteSettingsMissingTableTest extends TestCase
{
    public function test_accessor_returns_defaults_even_when_pengaturan_table_is_missing(): void
    {
        Schema::dropIfExists('pengaturan');

        /** @var SiteSettingsAccessor $settings */
        $settings = app(SiteSettingsAccessor::class);

        $this->assertSame('SMA AFBS', $settings->siteName());
        $this->assertSame('Sekolah Islam Berasrama', $settings->topbarBadge());
        $this->assertSame('Website resmi SMA AFBS.', $settings->defaultOgDescription());
        $this->assertSame('#16a34a', $settings->themeColor());
        $this->assertNull($settings->logoPath());
        $this->assertNull($settings->faviconPath());
        $this->assertNull($settings->defaultOgImage());
    }
}
