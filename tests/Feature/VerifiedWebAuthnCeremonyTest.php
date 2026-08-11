<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebAuthnChallenge;
use App\Models\WebAuthnCredential;
use App\Support\Auth\WebAuthn\VerifiedWebAuthnCeremony;
use App\Support\Auth\WebAuthn\WebAuthnCeremonyException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VerifiedWebAuthnCeremonyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->tables();
        config([
            'webauthn.enabled' => true,
            'webauthn.rp_id' => 'app.smaafbs.sch.id',
            'webauthn.origin' => 'https://app.smaafbs.sch.id',
        ]);
    }

    public function test_discoverable_login_options_do_not_expose_username_or_credential_list(): void
    {
        $issue = app(VerifiedWebAuthnCeremony::class)->issueDiscoverableAssertion(true);

        $this->assertSame('issued', $issue->status);
        $this->assertSame('app.smaafbs.sch.id', $issue->publicKeyOptions['rpId']);
        $this->assertSame('required', $issue->publicKeyOptions['userVerification']);
        $this->assertArrayNotHasKey('allowCredentials', $issue->publicKeyOptions);
        $this->assertDatabaseHas('webauthn_challenges', ['challenge_id' => $issue->challengeId, 'user_id' => null]);
    }

    public function test_registration_requires_resident_key_and_user_verification(): void
    {
        $user = User::query()->create(['name' => 'Guru', 'username' => 'guru.passkey', 'password' => 'secret']);
        $issue = app(VerifiedWebAuthnCeremony::class)->issueRegistration($user, true);

        $selection = $issue->publicKeyOptions['authenticatorSelection'];
        $this->assertSame('required', $selection['residentKey']);
        $this->assertTrue($selection['requireResidentKey']);
        $this->assertSame('required', $selection['userVerification']);
    }

    public function test_invalid_origin_is_rejected_before_attestation_is_trusted(): void
    {
        $user = User::query()->create(['name' => 'Guru', 'username' => 'guru.origin', 'password' => 'secret']);
        $issue = app(VerifiedWebAuthnCeremony::class)->issueRegistration($user, true);
        $clientData = $this->base64Url(json_encode([
            'type' => 'webauthn.create',
            'challenge' => $issue->challenge,
            'origin' => 'https://evil.example',
        ], JSON_THROW_ON_ERROR));

        try {
            app(VerifiedWebAuthnCeremony::class)->verifyRegistration($user, $issue->challengeId, [
                'client_data_json' => $clientData,
                'attestation_object' => $this->base64Url('invalid'),
            ]);
            $this->fail('Invalid origin seharusnya ditolak.');
        } catch (WebAuthnCeremonyException $exception) {
            $this->assertSame('invalid_origin', $exception->status);
        }

        $this->assertDatabaseCount('webauthn_credentials', 0);
        $this->assertNotNull(WebAuthnChallenge::query()->where('challenge_id', $issue->challengeId)->value('cancelled_at'));
    }

    public function test_five_verified_credentials_enforce_device_limit_but_legacy_rows_do_not_count(): void
    {
        $user = User::query()->create(['name' => 'Guru', 'username' => 'guru.limit', 'password' => 'secret']);

        WebAuthnCredential::query()->create(['user_id' => $user->id, 'credential_id' => 'legacy', 'public_key' => 'legacy']);

        foreach (range(1, 5) as $index) {
            WebAuthnCredential::query()->create([
                'user_id' => $user->id,
                'credential_id' => 'verified-'.$index,
                'public_key' => 'verified-key-'.$index,
                'credential_public_key' => 'verified-key-'.$index,
                'verified_at' => now(),
            ]);
        }

        $this->expectException(WebAuthnCeremonyException::class);
        app(VerifiedWebAuthnCeremony::class)->issueRegistration($user, true);
    }

    private function tables(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('username')->unique();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }
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

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
