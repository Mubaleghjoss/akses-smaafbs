<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Cookie\CookieJar;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class RememberedAdminUsernameTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('admin', 'web');
    }

    public function test_username_is_not_persisted_when_device_opt_in_is_off(): void
    {
        $user = User::query()->create([
            'name' => 'Admin No Remember',
            'username' => 'admin.no.remember',
            'password' => Hash::make('password-aman'),
        ]);
        $user->assignRole('admin');

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'password-aman')
            ->set('data.remember_username', false)
            ->call('authenticate');

        $queued = $this->queuedCookieValue('admin_remembered_usernames');

        $this->assertTrue($queued === null || $queued === '[]');
    }

    public function test_username_is_persisted_only_when_device_opt_in_is_enabled(): void
    {
        $user = User::query()->create([
            'name' => 'Admin Remember',
            'username' => 'admin.remember.me',
            'password' => Hash::make('password-aman'),
        ]);
        $user->assignRole('admin');

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->set('data.password', 'password-aman')
            ->set('data.remember_username', true)
            ->call('authenticate');

        $queued = $this->queuedCookieValue('admin_remembered_usernames');

        $this->assertNotNull($queued);
        $this->assertStringContainsString($user->username, (string) $queued);
    }

    public function test_remembered_username_selection_prefills_username_field(): void
    {
        Livewire::test(Login::class)
            ->set('rememberedUsernames', ['admin.tersimpan'])
            ->set('data.remembered_username', 'admin.tersimpan')
            ->assertSet('data.username', 'admin.tersimpan');
    }

    private function queuedCookieValue(string $cookieName): ?string
    {
        /** @var CookieJar $cookieJar */
        $cookieJar = app('cookie');

        foreach ($cookieJar->getQueuedCookies() as $cookie) {
            if ($cookie->getName() === $cookieName) {
                return $cookie->getValue();
            }
        }

        return null;
    }
}
