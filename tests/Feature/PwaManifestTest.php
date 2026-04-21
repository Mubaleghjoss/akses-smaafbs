<?php

namespace Tests\Feature;

use App\Models\Pengaturan;
use App\Support\SiteSettings\SiteSettingKeys;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PwaManifestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('pengaturan');
        Schema::create('pengaturan', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_pengaturan')->unique();
            $table->text('nilai_pengaturan')->nullable();
        });
    }

    public function test_manifest_uses_typed_branding_values_and_shell_defaults(): void
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
            'nilai_pengaturan' => '#2255AA',
        ]);

        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::FAVICON_PATH,
            'nilai_pengaturan' => 'site-branding/favicon/favicon-pwa.png',
        ]);

        $response = $this->get('/manifest.webmanifest');

        $response->assertOk();
        $this->assertStringContainsString('application/manifest+json', (string) $response->headers->get('content-type'));

        $payload = $response->json();

        $this->assertSame('Portal SMA AFBS', $payload['name']);
        $this->assertSame('AFBS', $payload['short_name']);
        $this->assertSame('standalone', $payload['display']);
        $this->assertSame('/', $payload['start_url']);
        $this->assertSame('/', $payload['scope']);
        $this->assertSame('#2255AA', $payload['theme_color']);
        $this->assertSame('/storage/site-branding/favicon/favicon-pwa.png', $payload['icons'][0]['src']);
    }
}
