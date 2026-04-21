<?php

namespace Tests\Feature;

use App\Filament\Resources\PengaturanResource\Pages\ListPengaturans;
use App\Models\User;
use App\Support\SiteSettings\SiteSettingKeys;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class PengaturanSiteBrandingPageTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        $this->recreatePengaturanTable();
    }

    public function test_admin_can_manage_curated_site_branding_fields_without_raw_key_value_flow(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Pengaturan Branding',
            'username' => 'admin-branding',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(ListPengaturans::class)
            ->assertSee('Ringkasan pengaturan')
            ->assertSee('Buka bagian')
            ->assertSet('site_name', 'SMA AFBS')
            ->assertSet('pwa_short_name', 'AFBS')
            ->set('site_name', 'SMA AFBS Digital')
            ->set('topbar_badge', 'Sekolah Islam Modern')
            ->set('topbar_text', 'Pusat informasi resmi untuk santri, wali, dan guru.')
            ->set('footer_primary_text', 'Yayasan AFBS')
            ->set('footer_secondary_text', 'Mencetak generasi berakhlak dan berprestasi.')
            ->set('default_seo_title', 'Portal SMA AFBS')
            ->set('default_seo_description', 'Website resmi SMA AFBS untuk informasi akademik.')
            ->set('default_og_title', 'SMA AFBS - Sekolah Islam Berasrama')
            ->set('default_og_description', 'Informasi sekolah, program, dan aktivitas santri.')
            ->set('default_og_image', 'https://cdn.example.test/afbs/og-default.png')
            ->set('theme_color', '#15803D')
            ->set('pwa_app_name', 'Portal SMA AFBS')
            ->set('pwa_short_name', 'AFBSApp')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pengaturan', [
            'nama_pengaturan' => SiteSettingKeys::SITE_NAME,
            'nilai_pengaturan' => 'SMA AFBS Digital',
        ]);

        $this->assertDatabaseHas('pengaturan', [
            'nama_pengaturan' => SiteSettingKeys::TOPBAR_BADGE,
            'nilai_pengaturan' => 'Sekolah Islam Modern',
        ]);

        $this->assertDatabaseHas('pengaturan', [
            'nama_pengaturan' => SiteSettingKeys::THEME_COLOR,
            'nilai_pengaturan' => '#15803D',
        ]);

        $this->assertDatabaseHas('pengaturan', [
            'nama_pengaturan' => SiteSettingKeys::PWA_SHORT_NAME,
            'nilai_pengaturan' => 'AFBSApp',
        ]);

        $this->assertDatabaseHas('pengaturan', [
            'nama_pengaturan' => SiteSettingKeys::DEFAULT_OG_IMAGE,
            'nilai_pengaturan' => 'https://cdn.example.test/afbs/og-default.png',
        ]);
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
