<?php

namespace Tests\Feature;

use App\Contracts\Auth\WebAuthnChallengeFlow;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\ManagePasskeys;
use App\Models\User;
use App\Models\WebAuthnCredential;
use App\Support\Auth\WebAuthn\WebAuthnAssertionResult;
use App\Support\Auth\WebAuthn\WebAuthnChallengeIssueResult;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class AdminPasskeyLoginTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootstrapUserAndPermissionTables();
        $this->runWebAuthnMigrations();
        config(['webauthn.enabled' => true]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Role::findOrCreate('admin', 'web');
    }

    public function test_account_page_marks_unverified_legacy_credential_for_reregistration(): void
    {
        $user = $this->adminUser('legacy.passkey');
        WebAuthnCredential::query()->create([
            'user_id' => $user->id,
            'credential_id' => 'legacy-credential',
            'public_key' => 'legacy-public-key',
            'sign_count' => 0,
        ]);

        $this->actingAs($user);

        Livewire::test(ManagePasskeys::class)
            ->assertSee('Perlu Daftar Ulang')
            ->assertSee('Aktifkan Passkey di Perangkat Ini')
            ->assertDontSee('Credential ID')
            ->assertDontSee('Public Key');
    }

    public function test_browser_registration_stores_only_server_verified_credential(): void
    {
        $user = $this->adminUser('register.passkey');
        $this->actingAs($user);

        $credential = WebAuthnCredential::query()->create([
            'user_id' => $user->id,
            'credential_id' => 'verified-credential',
            'public_key' => 'verified-key',
            'credential_public_key' => 'verified-key',
            'signature_counter' => 0,
            'sign_count' => 0,
            'user_handle' => 'dXNlcjox',
            'device_name' => 'Laptop Guru',
            'verified_at' => now(),
        ]);

        $flow = Mockery::mock(WebAuthnChallengeFlow::class);
        $flow->shouldReceive('issueRegistrationChallenge')->once()->andReturn(new WebAuthnChallengeIssueResult(
            WebAuthnChallengeIssueResult::ISSUED,
            '11111111-1111-4111-8111-111111111111',
            'challenge',
            true,
            ['challenge' => 'Y2hhbGxlbmdl'],
        ));
        $flow->shouldReceive('verifyRegistration')->once()->andReturn($credential);
        $this->app->instance(WebAuthnChallengeFlow::class, $flow);

        Livewire::test(ManagePasskeys::class)
            ->set('deviceName', 'Laptop Guru')
            ->call('beginPasskeyRegistration', true)
            ->call('completePasskeyRegistration', '11111111-1111-4111-8111-111111111111', [
                'client_data_json' => 'Y2xpZW50',
                'attestation_object' => 'YXR0ZXN0YXRpb24',
                'transports' => ['internal'],
            ])
            ->assertSet('registrationStatus', 'completed')
            ->assertSee('Laptop Guru');
    }

    public function test_discoverable_passkey_login_does_not_require_username(): void
    {
        $user = $this->adminUser('discoverable.passkey');
        $credential = new WebAuthnCredential(['credential_id' => 'credential-login']);

        $flow = Mockery::mock(WebAuthnChallengeFlow::class);
        $flow->shouldReceive('issueDiscoverableAssertionChallenge')->once()->andReturn(new WebAuthnChallengeIssueResult(
            WebAuthnChallengeIssueResult::ISSUED,
            '22222222-2222-4222-8222-222222222222',
            'challenge',
            true,
            ['challenge' => 'Y2hhbGxlbmdl', 'rpId' => 'app.smaafbs.sch.id', 'allowCredentials' => []],
        ));
        $flow->shouldReceive('verifyDiscoverableAssertion')->once()->andReturn(new WebAuthnAssertionResult(
            WebAuthnAssertionResult::VERIFIED,
            false,
            $user,
            $credential,
        ));
        $this->app->instance(WebAuthnChallengeFlow::class, $flow);

        Livewire::test(Login::class)
            ->call('beginPasskeyLogin', true)
            ->assertSet('passkeyStatus', 'issued')
            ->call('completePasskeyLogin', 'credential-login', '22222222-2222-4222-8222-222222222222', [
                'credential_id' => 'credential-login',
                'raw_id' => 'Y3JlZGVudGlhbA',
                'client_data_json' => 'Y2xpZW50',
                'authenticator_data' => 'YXV0aA',
                'signature' => 'c2lnbmF0dXJl',
                'user_handle' => 'dXNlcjox',
            ]);

        $this->assertAuthenticatedAs($user);
    }

    private function adminUser(string $username): User
    {
        $user = User::query()->create(['name' => 'Admin Passkey', 'username' => $username, 'password' => Hash::make('password')]);
        $user->assignRole('admin');

        return $user;
    }

    private function runWebAuthnMigrations(): void
    {
        if (! Schema::hasTable('webauthn_credentials')) {
            (require database_path('migrations/2026_03_31_230000_create_webauthn_credentials_table.php'))->up();
        }
        if (! Schema::hasTable('webauthn_challenges')) {
            (require database_path('migrations/2026_03_31_230100_create_webauthn_challenges_table.php'))->up();
        }
        if (! Schema::hasColumn('webauthn_credentials', 'credential_public_key')) {
            (require database_path('migrations/2026_08_11_120000_upgrade_webauthn_credentials_for_verified_passkeys.php'))->up();
        }
    }
}
