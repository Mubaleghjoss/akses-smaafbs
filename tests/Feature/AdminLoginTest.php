<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\Pengaturan;
use App\Models\User;
use App\Support\Security\EndpointProtectionPolicy;
use App\Support\SiteSettings\SiteSettingKeys;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('username', 50)->unique();
                $table->string('email')->nullable()->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pengaturan')) {
            Schema::create('pengaturan', function (Blueprint $table): void {
                $table->id();
                $table->string('nama_pengaturan')->unique();
                $table->text('nilai_pengaturan')->nullable();
            });
        }

        $this->runPermissionMigration();
        config(['webauthn.enabled' => true]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_login_page_displays_custom_branding(): void
    {
        $response = $this->get('/admin/login');

        $response
            ->assertOk()
            ->assertSee('Selamat Datang')
            ->assertSee('Masuk ke Panel Admin SMA AFBS.')
            ->assertSee('Login dengan Sidik Jari / Passkey')
            ->assertDontSee('dukungan PWA dan opsi autentikasi biometrik');
    }

    public function test_admin_login_page_uses_site_setting_icon_for_panel_head(): void
    {
        \Illuminate\Support\Facades\DB::table('pengaturan')->insert([
            'nama_pengaturan' => SiteSettingKeys::FAVICON_PATH,
            'nilai_pengaturan' => 'site-branding/favicon/admin-icon.png',
        ]);

        $response = $this->get('/admin/login');

        $response
            ->assertOk()
            ->assertSee('<link rel="icon" href="/storage/site-branding/favicon/admin-icon.png">', false)
            ->assertSee('<link rel="shortcut icon" href="/storage/site-branding/favicon/admin-icon.png">', false)
            ->assertSee('<link rel="apple-touch-icon" href="/storage/site-branding/favicon/admin-icon.png">', false)
            ->assertSee('filament-admin-auth.css', false);
    }

    public function test_admin_login_page_uses_site_setting_name_for_admin_title(): void
    {
        Pengaturan::query()->create([
            'nama_pengaturan' => SiteSettingKeys::SITE_NAME,
            'nilai_pengaturan' => 'Portal Komite AFBS',
        ]);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Portal Komite AFBS Admin');
    }

    public function test_password_error_is_shown_on_password_field_when_username_exists(): void
    {
        $user = User::query()->create([
            'name' => 'Admin SMA AFBS',
            'username' => 'admin.afbs',
            'email' => 'admin@example.com',
            'password' => Hash::make('rahasia-benar'),
        ]);

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'password-salah')
            ->call('authenticate')
            ->assertHasErrors(['data.password'])
            ->assertSee('Password yang Anda masukkan salah.');
    }

    public function test_username_error_is_shown_when_account_is_not_found(): void
    {
        Livewire::test(Login::class)
            ->set('data.username', 'tidak-ada')
            ->set('data.password', 'bebas')
            ->call('authenticate')
            ->assertHasErrors(['data.username'])
            ->assertSee('Username tidak ditemukan.');
    }

    public function test_endpoint_protection_matrix_is_discoverable_from_one_policy_location(): void
    {
        $categories = EndpointProtectionPolicy::endpointCategories();

        $this->assertArrayHasKey('admin_auth', $categories);
        $this->assertArrayHasKey('admin_exports_downloads', $categories);
        $this->assertSame('/admin/login', $categories['admin_auth']['routes'][0]);
        $this->assertSame(5, EndpointProtectionPolicy::adminLoginAttempts());
        $this->assertSame(60, EndpointProtectionPolicy::adminLoginDecaySeconds());
    }

    public function test_repeated_invalid_login_attempts_lock_only_same_username_and_ip_key(): void
    {
        $attempts = EndpointProtectionPolicy::adminLoginAttempts();

        $lockedUser = User::query()->create([
            'name' => 'Locked Admin',
            'username' => 'locked.admin',
            'email' => 'locked-admin@example.com',
            'password' => Hash::make('rahasia-benar'),
        ]);

        $otherUser = User::query()->create([
            'name' => 'Other Admin',
            'username' => 'other.admin',
            'email' => 'other-admin@example.com',
            'password' => Hash::make('rahasia-benar'),
        ]);

        $lockedKey = EndpointProtectionPolicy::adminLoginRateLimitKey(
            username: $lockedUser->username,
            ip: '127.0.0.1',
            component: Login::class,
            method: 'authenticate',
        );
        $otherKey = EndpointProtectionPolicy::adminLoginRateLimitKey(
            username: $otherUser->username,
            ip: '127.0.0.1',
            component: Login::class,
            method: 'authenticate',
        );

        RateLimiter::clear($lockedKey);
        RateLimiter::clear($otherKey);

        for ($i = 0; $i < $attempts; $i++) {
            Livewire::test(Login::class)
                ->set('data.username', $lockedUser->username)
                ->set('data.password', 'password-salah')
                ->call('authenticate')
                ->assertHasErrors(['data.password']);
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($lockedKey, $attempts));

        Livewire::test(Login::class)
            ->set('data.username', $otherUser->username)
            ->set('data.password', 'password-salah')
            ->call('authenticate')
            ->assertHasErrors(['data.password']);

        $this->assertFalse(RateLimiter::tooManyAttempts($otherKey, $attempts));
    }

    public function test_valid_login_succeeds_after_decay_recovery_window(): void
    {
        config(['endpoint_protection.endpoint_categories.admin_auth.livewire_rate_limit_attempts' => 2]);
        config(['endpoint_protection.endpoint_categories.admin_auth.livewire_rate_limit_decay_seconds' => 3]);

        $this->runPermissionMigration();
        Role::findOrCreate('admin', 'web');

        $user = User::query()->create([
            'name' => 'Recoverable Admin',
            'username' => 'recover.admin',
            'email' => 'recover-admin@example.com',
            'password' => Hash::make('rahasia-benar'),
        ]);
        $user->assignRole('admin');

        $attempts = EndpointProtectionPolicy::adminLoginAttempts();
        $key = EndpointProtectionPolicy::adminLoginRateLimitKey(
            username: $user->username,
            ip: '127.0.0.1',
            component: Login::class,
            method: 'authenticate',
        );

        RateLimiter::clear($key);

        for ($i = 0; $i < $attempts; $i++) {
            Livewire::test(Login::class)
                ->set('data.username', $user->username)
                ->set('data.password', 'password-salah')
                ->call('authenticate')
                ->assertHasErrors(['data.password']);
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($key, $attempts));

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'rahasia-benar')
            ->call('authenticate');

        $this->assertGuest();

        sleep(4);

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'rahasia-benar')
            ->call('authenticate');

        $this->assertAuthenticatedAs($user);
        $this->assertFalse(RateLimiter::tooManyAttempts($key, $attempts));
    }

    public function test_named_limiters_are_registered_from_central_policy(): void
    {
        $this->assertNotNull(RateLimiter::limiter('admin_exports'));
        $this->assertNotNull(RateLimiter::limiter('public_reads'));
    }

    public function test_graceful_degradation_switch_is_project_owned_and_off_by_default(): void
    {
        config(['endpoint_protection.graceful_degradation.enabled' => false]);
        config([
            'endpoint_protection.graceful_degradation.profiles.admin_heavy_widgets.menu.skip_expensive_dynamic_sections' => true,
            'endpoint_protection.graceful_degradation.profiles.admin_heavy_widgets.dashboard.skip_expensive_widgets' => true,
        ]);

        $this->assertFalse(EndpointProtectionPolicy::shouldSkipExpensiveAdminMenuSections());
        $this->assertFalse(EndpointProtectionPolicy::shouldSkipExpensiveAdminDashboardWidgets());

        config(['endpoint_protection.graceful_degradation.enabled' => true]);

        $this->assertTrue(EndpointProtectionPolicy::shouldSkipExpensiveAdminMenuSections());
        $this->assertTrue(EndpointProtectionPolicy::shouldSkipExpensiveAdminDashboardWidgets());
    }
}
