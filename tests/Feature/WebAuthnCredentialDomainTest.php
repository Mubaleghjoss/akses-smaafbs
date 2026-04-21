<?php

namespace Tests\Feature;

use App\Contracts\Auth\WebAuthnCredentialDomain;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class WebAuthnCredentialDomainTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        $this->runWebAuthnMigrations();
    }

    public function test_credentials_can_be_enrolled_listed_and_revoked_for_user(): void
    {
        $user = User::query()->create([
            'name' => 'Admin Passkey',
            'username' => 'admin-passkey',
            'password' => Hash::make('rahasia-benaar'),
        ]);

        $domain = app(WebAuthnCredentialDomain::class);

        $domain->enroll($user, [
            'credential_id' => 'cred-admin-1',
            'public_key' => 'public-key-1',
            'transports' => ['internal', 'usb'],
            'label' => 'Laptop Kantor',
            'sign_count' => 5,
        ]);

        $domain->enroll($user, [
            'credential_id' => 'cred-admin-2',
            'public_key' => 'public-key-2',
            'transports' => ['hybrid'],
            'label' => 'HP Pribadi',
        ]);

        $active = $domain->listForUser($user);

        $this->assertCount(2, $active);
        $this->assertSame(['cred-admin-2', 'cred-admin-1'], $active->pluck('credential_id')->all());

        $this->assertTrue($domain->revoke($user, 'cred-admin-1'));
        $this->assertFalse($domain->revoke($user, 'credential-tidak-ada'));

        $activeAfterRevoke = $domain->listForUser($user);
        $all = $domain->listForUser($user, includeRevoked: true);

        $this->assertCount(1, $activeAfterRevoke);
        $this->assertSame('cred-admin-2', $activeAfterRevoke->first()->credential_id);
        $this->assertCount(2, $all);
        $this->assertNotNull($all->firstWhere('credential_id', 'cred-admin-1')?->revoked_at);
    }

    public function test_same_credential_id_cannot_be_claimed_by_other_user(): void
    {
        $domain = app(WebAuthnCredentialDomain::class);

        $firstUser = User::query()->create([
            'name' => 'Pemilik Awal',
            'username' => 'pemilik-awal',
            'password' => Hash::make('password-1'),
        ]);

        $secondUser = User::query()->create([
            'name' => 'Pengguna Lain',
            'username' => 'pengguna-lain',
            'password' => Hash::make('password-2'),
        ]);

        $domain->enroll($firstUser, [
            'credential_id' => 'credential-global-1',
            'public_key' => 'public-key-owner-1',
        ]);

        $this->expectException(DomainException::class);

        $domain->enroll($secondUser, [
            'credential_id' => 'credential-global-1',
            'public_key' => 'public-key-owner-2',
        ]);
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
