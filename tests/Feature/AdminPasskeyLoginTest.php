<?php

namespace Tests\Feature;

use App\Contracts\Auth\WebAuthnCredentialDomain;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\ManagePasskeys;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
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
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_authenticated_internal_user_can_enroll_and_revoke_passkey_from_account_surface(): void
    {
        $user = User::query()->create([
            'name' => 'Guru Internal',
            'username' => 'guru.internal',
            'password' => Hash::make('password-aman'),
        ]);
        $user->assignRole('guru');

        $this->actingAs($user);

        Livewire::test(ManagePasskeys::class)
            ->set('label', 'Laptop Kantor')
            ->set('credentialId', 'cred-manage-1')
            ->set('publicKey', 'public-key-manage-1')
            ->set('transports', 'internal, usb')
            ->set('signCount', 2)
            ->call('enrollPasskey')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('webauthn_credentials', [
            'user_id' => $user->getKey(),
            'credential_id' => 'cred-manage-1',
            'revoked_at' => null,
        ]);

        Livewire::test(ManagePasskeys::class)
            ->call('revokePasskey', 'cred-manage-1');

        $this->assertDatabaseMissing('webauthn_credentials', [
            'user_id' => $user->getKey(),
            'credential_id' => 'cred-manage-1',
            'revoked_at' => null,
        ]);
    }

    public function test_admin_login_can_authenticate_using_passkey_without_removing_password_flow(): void
    {
        Role::findOrCreate('admin', 'web');
        $keyPair = $this->test_key_pair();

        $user = User::query()->create([
            'name' => 'Admin Passkey Login',
            'username' => 'admin.passkey.login',
            'password' => Hash::make('password-aman'),
        ]);
        $user->assignRole('admin');

        app(WebAuthnCredentialDomain::class)->enroll($user, [
            'credential_id' => 'cred-admin-login-1',
            'public_key' => $keyPair['public_key'],
            'sign_count' => 8,
        ]);

        $component = Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->call('beginPasskeyLogin')
            ->assertSet('passkeyStatus', 'issued');

        $challengeId = (string) $component->get('pendingPasskeyChallengeId');
        $challenge = (string) $component->get('pendingPasskeyChallenge');

        $component->call(
            'completePasskeyLogin',
            'cred-admin-login-1',
            9,
            $challengeId,
            $challenge,
            $this->browserAssertionPayload('cred-admin-login-1', $challenge, 9, $keyPair['private_key']),
        );

        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_login_passkey_allows_missing_sign_count_without_forcing_regression(): void
    {
        Role::findOrCreate('admin', 'web');
        $keyPair = $this->test_key_pair();

        $user = User::query()->create([
            'name' => 'Admin Passkey Tanpa Sign Count',
            'username' => 'admin.passkey.no-sign-count',
            'password' => Hash::make('password-aman'),
        ]);
        $user->assignRole('admin');

        app(WebAuthnCredentialDomain::class)->enroll($user, [
            'credential_id' => 'cred-admin-login-null-signcount-1',
            'public_key' => $keyPair['public_key'],
            'sign_count' => 8,
        ]);

        $component = Livewire::test(Login::class)
            ->set('data.username', $user->username)
            ->call('beginPasskeyLogin')
            ->assertSet('passkeyStatus', 'issued');

        $challengeId = (string) $component->get('pendingPasskeyChallengeId');
        $challenge = (string) $component->get('pendingPasskeyChallenge');

        $component->call(
            'completePasskeyLogin',
            'cred-admin-login-null-signcount-1',
            null,
            $challengeId,
            $challenge,
            $this->browserAssertionPayload('cred-admin-login-null-signcount-1', $challenge, 8, $keyPair['private_key']),
        );

        $this->assertAuthenticatedAs($user);
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

    /**
     * @return array<string, string|int>
     */
    private function browserAssertionPayload(string $credentialId, string $challenge, int $signCount, string $privateKey): array
    {
        $rpId = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $rpIdHash = hash('sha256', (string) $rpId, true);
        $flags = chr(0x01);
        $authenticatorData = $rpIdHash.$flags.pack('N', $signCount);

        $clientDataJson = json_encode([
            'type' => 'webauthn.get',
            'challenge' => $challenge,
            'origin' => 'http://'.$rpId,
        ]);

        $signedData = $authenticatorData.hash('sha256', (string) $clientDataJson, true);
        $signature = '';
        $privateKeyResource = openssl_pkey_get_private($privateKey);
        $this->assertNotFalse($privateKeyResource);
        $signed = openssl_sign($signedData, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);

        $this->assertTrue($signed === true && $signature !== '');

        return [
            'credential_id' => $credentialId,
            'raw_id' => $credentialId,
            'client_data_json' => $this->toBase64Url((string) $clientDataJson),
            'authenticator_data' => $this->toBase64Url($authenticatorData),
            'signature' => $this->toBase64Url($signature),
            'sign_count' => $signCount,
        ];
    }

    /**
     * @return array{private_key: string, public_key: string}
     */
    private function test_key_pair(): array
    {
        return [
            'private_key' => <<<'KEY'
-----BEGIN RSA PRIVATE KEY-----
MIIBOgIBAAJBAKj34GkxFhD90vcNLYLInFEX6Ppy1tPf9Cnzj4p4WGeKLs1Pt8Qu
KUpRKfFLfRYC9AIKjbJTWit+CqvjWYzvQwECAwEAAQJAIJLixBy2qpFoS4DSmoEm
o3qGy0t6z09AIJtH+5OeRV1be+N4cDYJKffGzDa88vQENZiRm0GRq6a+HPGQMd2k
TQIhAKMSvzIBnni7ot/OSie2TmJLY4SwTQAevXysE2RbFDYdAiEBCUEaRQnMnbp7
9mxDXDf6AU0cN/RPBjb9qSHDcWZHGzUCIG2Es59z8ugGrDY+pxLQnwfotadxd+Uy
v/Ow5T0q5gIJAiEAyS4RaI9YG8EWx/2w0T67ZUVAw8eOMB6BIUg0Xcu+3okCIBOs
/5OiPgoTdSy7bcF9IGpSE8ZgGKzgYQVZeN97YE00
-----END RSA PRIVATE KEY-----
KEY,
            'public_key' => <<<'KEY'
-----BEGIN PUBLIC KEY-----
MFwwDQYJKoZIhvcNAQEBBQADSwAwSAJBAKj34GkxFhD90vcNLYLInFEX6Ppy1tPf
9Cnzj4p4WGeKLs1Pt8QuKUpRKfFLfRYC9AIKjbJTWit+CqvjWYzvQwECAwEAAQ==
-----END PUBLIC KEY-----
KEY,
        ];
    }

    private function toBase64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
