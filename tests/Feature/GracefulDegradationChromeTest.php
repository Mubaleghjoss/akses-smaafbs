<?php

namespace Tests\Feature;

use App\Filament\Pages\DashboardProker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GracefulDegradationChromeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createPublicHomepageTables();
        $this->createUsersTable();
    }

    public function test_dashboard_proker_header_widgets_are_disabled_in_degraded_mode(): void
    {
        config()->set('endpoint_protection.graceful_degradation.enabled', true);
        config()->set('endpoint_protection.graceful_degradation.profiles.admin_heavy_widgets.dashboard.skip_expensive_widgets', true);

        $dashboard = new DashboardProker;

        $this->assertTrue($dashboard->isDegradedDashboardMode());
        $this->assertSame([], $dashboard->getHeaderWidgets());
    }

    public function test_dashboard_proker_header_widgets_stay_available_when_degraded_mode_is_off(): void
    {
        config()->set('endpoint_protection.graceful_degradation.enabled', false);
        config()->set('endpoint_protection.graceful_degradation.profiles.admin_heavy_widgets.dashboard.skip_expensive_widgets', true);

        $dashboard = new DashboardProker;

        $this->assertFalse($dashboard->isDegradedDashboardMode());
        $this->assertNotEmpty($dashboard->getHeaderWidgets());
    }

    public function test_public_homepage_renders_and_keeps_core_navigation_under_degraded_chrome(): void
    {
        config()->set('endpoint_protection.graceful_degradation.enabled', true);
        config()->set('endpoint_protection.graceful_degradation.profiles.public_chrome.layout.skip_decorative_surfaces', true);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Login');
        $response->assertDontSee('blur-3xl');
    }

    public function test_admin_login_renders_with_degraded_mode_enabled(): void
    {
        config()->set('endpoint_protection.graceful_degradation.enabled', true);
        config()->set('endpoint_protection.graceful_degradation.profiles.admin_heavy_widgets.menu.skip_expensive_dynamic_sections', true);
        config()->set('endpoint_protection.graceful_degradation.profiles.admin_heavy_widgets.dashboard.skip_expensive_widgets', true);

        $this->get('/admin/login')->assertOk();
    }

    protected function createPublicHomepageTables(): void
    {
        if (! Schema::hasTable('data_siswa')) {
            Schema::create('data_siswa', function (Blueprint $table): void {
                $table->id();
            });
        }

        if (! Schema::hasTable('berita')) {
            Schema::create('berita', function (Blueprint $table): void {
                $table->id();
                $table->string('status')->nullable();
                $table->date('tanggal_berita')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('perpustakaan_buku')) {
            Schema::create('perpustakaan_buku', function (Blueprint $table): void {
                $table->id();
            });
        }
    }

    protected function createUsersTable(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }
}
