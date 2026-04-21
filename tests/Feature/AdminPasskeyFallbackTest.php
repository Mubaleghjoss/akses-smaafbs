<?php

namespace Tests\Feature;

use App\Contracts\Auth\WebAuthnCredentialDomain;
use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class AdminPasskeyFallbackTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        $this->runWebAuthnMigrations();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('admin', 'web');
    }

    public function test_unenrolled_user_gets_safe_password_fallback_message(): void
    {
        $user = User::query()->create([
            'name' => 'Admin Tanpa Passkey',
            'username' => 'admin.tanpa.passkey',
            'password' => Hash::make('password-aman'),
        ]);
        $user->assignRole('admin');

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->call('beginPasskeyLogin')
            ->assertSet('passkeyStatus', 'unenrolled')
            ->assertSet('passkeyCanFallbackToPassword', true);
    }

    public function test_unsupported_browser_flow_degrades_to_password_guidance(): void
    {
        $user = User::query()->create([
            'name' => 'Admin Unsupported Browser',
            'username' => 'admin.unsupported.browser',
            'password' => Hash::make('password-aman'),
        ]);
        $user->assignRole('admin');

        app(WebAuthnCredentialDomain::class)->enroll($user, [
            'credential_id' => 'cred-unsupported-1',
            'public_key' => 'public-key-unsupported-1',
        ]);

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->call('beginPasskeyLogin', false)
            ->assertSet('passkeyStatus', 'unsupported_browser')
            ->assertSet('passkeyCanFallbackToPassword', true);
    }

    public function test_revoked_credential_flow_stays_on_password_fallback_path(): void
    {
        $user = User::query()->create([
            'name' => 'Admin Revoked',
            'username' => 'admin.revoked',
            'password' => Hash::make('password-aman'),
        ]);
        $user->assignRole('admin');

        $domain = app(WebAuthnCredentialDomain::class);
        $domain->enroll($user, [
            'credential_id' => 'cred-revoked-fallback-1',
            'public_key' => 'public-key-revoked-fallback-1',
        ]);
        $domain->revoke($user, 'cred-revoked-fallback-1');

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->call('beginPasskeyLogin')
            ->assertSet('passkeyStatus', 'credential_revoked')
            ->assertSet('passkeyCanFallbackToPassword', true);

        $this->assertGuest();
    }

    public function test_cancelled_passkey_ceremony_degrades_cleanly_to_password_path(): void
    {
        $user = User::query()->create([
            'name' => 'Admin Cancel Flow',
            'username' => 'admin.cancel.flow',
            'password' => Hash::make('password-aman'),
        ]);
        $user->assignRole('admin');

        app(WebAuthnCredentialDomain::class)->enroll($user, [
            'credential_id' => 'cred-cancel-1',
            'public_key' => 'public-key-cancel-1',
        ]);

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->call('beginPasskeyLogin')
            ->call('cancelPasskeyLogin')
            ->assertSet('passkeyStatus', 'ceremony_cancelled')
            ->assertSet('passkeyCanFallbackToPassword', true);
    }

    public function test_synthetic_credential_only_submission_is_rejected_as_invalid_challenge(): void
    {
        $user = User::query()->create([
            'name' => 'Admin Synthetic Submission',
            'username' => 'admin.synthetic.only',
            'password' => Hash::make('password-aman'),
        ]);
        $user->assignRole('admin');

        app(WebAuthnCredentialDomain::class)->enroll($user, [
            'credential_id' => 'cred-synthetic-only-1',
            'public_key' => 'public-key-synthetic-only-1',
            'sign_count' => 5,
        ]);

        Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->call('beginPasskeyLogin')
            ->call('completePasskeyLogin', 'cred-synthetic-only-1', 6)
            ->assertSet('passkeyStatus', 'invalid_challenge')
            ->assertSet('passkeyCanFallbackToPassword', true);

        $this->assertGuest();
    }

    protected function runWebAuthnMigrations(): void
    {
        if (! Schema::hasTable('webauthn_credentials')) {
            $credentialsMigration = require database_path('migrations/2026_03_31_230000_create_webauthn_credentials_table.php');
            $credentialsMigration->up();
        }

        if (! Schema::hasTable('webauthn_challenges')) {
            $challengeMigration = require database_path('migrations/2026_03_31_230100_create_webauthn_challenges_table.php');
            $challengeMigration->up();
        }
    }
}
