<?php

namespace Tests\Feature;

use App\Contracts\SiteSettingsAccessor;
use App\Filament\Resources\PengaturanResource\Pages\ListPengaturans;
use App\Models\Pengaturan;
use App\Models\User;
use App\Support\SiteSettings\SiteSettingKeys;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class PengaturanBrandAssetUploadTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->bootstrapUserAndPermissionTables();
        $this->recreatePengaturanTable();
    }

    public function test_admin_can_upload_logo_and_favicon_as_separate_site_brand_assets(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Asset Branding',
            'username' => 'admin-brand-assets',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(ListPengaturans::class)
            ->set('logo_upload', UploadedFile::fake()->image('frontend-logo.png', 480, 220))
            ->set('favicon_upload', UploadedFile::fake()->image('favicon.png', 64, 64))
            ->call('save')
            ->assertHasNoErrors();

        $logoPath = (string) Pengaturan::value(SiteSettingKeys::LOGO_PATH);
        $faviconPath = (string) Pengaturan::value(SiteSettingKeys::FAVICON_PATH);

        $this->assertNotSame('', $logoPath);
        $this->assertNotSame('', $faviconPath);
        $this->assertStringStartsWith('site-branding/logo/', $logoPath);
        $this->assertStringStartsWith('site-branding/favicon/', $faviconPath);
        $this->assertNotSame($logoPath, $faviconPath);

        Storage::disk('public')->assertExists($logoPath);
        Storage::disk('public')->assertExists($faviconPath);

        /** @var SiteSettingsAccessor $settings */
        $settings = app(SiteSettingsAccessor::class);

        $this->assertSame('/storage/'.$logoPath, $settings->logoPath());
        $this->assertSame('/storage/'.$faviconPath, $settings->faviconPath());
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
