<?php

namespace Tests\Feature;

use App\Contracts\Auth\WebAuthnChallengeFlow;
use App\Contracts\Auth\WebAuthnCredentialDomain;
use App\Models\User;
use App\Support\Auth\WebAuthn\WebAuthnAssertionResult;
use App\Support\Auth\WebAuthn\WebAuthnChallengeIssueResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class WebAuthnChallengeFlowTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        $this->runWebAuthnMigrations();
    }

    public function test_assertion_challenge_can_be_issued_and_verified(): void
    {
        $keyPair = $this->test_key_pair();

        $user = User::query()->create([
            'name' => 'Pengguna Tantangan',
            'username' => 'pengguna-tantangan',
            'password' => Hash::make('password-tetap-aman'),
        ]);

        $credentialDomain = app(WebAuthnCredentialDomain::class);
        $challengeFlow = app(WebAuthnChallengeFlow::class);

        $credentialDomain->enroll($user, [
            'credential_id' => 'cred-verify-1',
            'public_key' => $keyPair['public_key'],
            'sign_count' => 10,
        ]);

        $issue = $challengeFlow->issueAssertionChallenge($user, browserSupported: true, context: ['origin' => 'admin_login']);

        $this->assertSame(WebAuthnChallengeIssueResult::ISSUED, $issue->status);
        $this->assertFalse($issue->canFallbackToPassword);
        $this->assertNotNull($issue->challenge);

        $verify = $challengeFlow->verifyAssertion(
            challengeId: $issue->challengeId,
            clientChallenge: (string) $issue->challenge,
            credentialId: 'cred-verify-1',
            payload: $this->browserAssertionPayload(
                credentialId: 'cred-verify-1',
                challenge: (string) $issue->challenge,
                signCount: 11,
                privateKey: $keyPair['private_key'],
            ),
        );

        $this->assertSame(WebAuthnAssertionResult::VERIFIED, $verify->status);
        $this->assertTrue($verify->verified());
        $this->assertFalse($verify->canFallbackToPassword);
        $this->assertNotNull($credentialDomain->findActiveByCredentialId('cred-verify-1')?->last_used_at);
    }

    public function test_invalid_challenge_is_rejected_with_password_fallback_signal(): void
    {
        $user = User::query()->create([
            'name' => 'Admin Invalid Challenge',
            'username' => 'admin-invalid-challenge',
            'password' => Hash::make('masih-aman'),
        ]);

        $credentialDomain = app(WebAuthnCredentialDomain::class);
        $challengeFlow = app(WebAuthnChallengeFlow::class);

        $credentialDomain->enroll($user, [
            'credential_id' => 'cred-invalid-1',
            'public_key' => 'public-key-invalid-1',
        ]);

        $issue = $challengeFlow->issueAssertionChallenge($user, browserSupported: true);

        $invalidResult = $challengeFlow->verifyAssertion(
            challengeId: $issue->challengeId,
            clientChallenge: 'challenge-yang-salah',
            credentialId: 'cred-invalid-1',
        );

        $this->assertSame(WebAuthnAssertionResult::INVALID_CHALLENGE, $invalidResult->status);
        $this->assertTrue($invalidResult->canFallbackToPassword);
        $this->assertTrue(Hash::check('masih-aman', $user->password));
    }

    public function test_unsupported_browser_returns_deterministic_fallback_result(): void
    {
        $user = User::query()->create([
            'name' => 'Admin Unsupported Browser',
            'username' => 'admin-unsupported-browser',
            'password' => Hash::make('password-unsupported-browser'),
        ]);

        $challengeFlow = app(WebAuthnChallengeFlow::class);

        $issue = $challengeFlow->issueAssertionChallenge($user, browserSupported: false);

        $this->assertSame(WebAuthnChallengeIssueResult::UNSUPPORTED_BROWSER, $issue->status);
        $this->assertNull($issue->challenge);
        $this->assertTrue($issue->canFallbackToPassword);

        $verify = $challengeFlow->verifyAssertion(
            challengeId: $issue->challengeId,
            clientChallenge: 'apa-saja',
            credentialId: 'credential-apa-saja',
        );

        $this->assertSame(WebAuthnAssertionResult::UNSUPPORTED_BROWSER, $verify->status);
        $this->assertTrue($verify->canFallbackToPassword);
    }

    public function test_cancelled_ceremony_is_rejected_without_consuming_password_path(): void
    {
        $user = User::query()->create([
            'name' => 'Admin Cancel Flow',
            'username' => 'admin-cancel-flow',
            'password' => Hash::make('password-cancel'),
        ]);

        $credentialDomain = app(WebAuthnCredentialDomain::class);
        $challengeFlow = app(WebAuthnChallengeFlow::class);

        $credentialDomain->enroll($user, [
            'credential_id' => 'cred-cancel-1',
            'public_key' => 'public-key-cancel-1',
        ]);

        $issue = $challengeFlow->issueAssertionChallenge($user, browserSupported: true);

        $challengeFlow->cancel($issue->challengeId, 'user_cancelled');

        $result = $challengeFlow->verifyAssertion(
            challengeId: $issue->challengeId,
            clientChallenge: (string) $issue->challenge,
            credentialId: 'cred-cancel-1',
        );

        $this->assertSame(WebAuthnAssertionResult::CEREMONY_CANCELLED, $result->status);
        $this->assertTrue($result->canFallbackToPassword);
    }

    public function test_revoked_credential_returns_deterministic_rejection(): void
    {
        $user = User::query()->create([
            'name' => 'Admin Revoked Credential',
            'username' => 'admin-revoked-credential',
            'password' => Hash::make('password-revoked'),
        ]);

        $credentialDomain = app(WebAuthnCredentialDomain::class);
        $challengeFlow = app(WebAuthnChallengeFlow::class);

        $credentialDomain->enroll($user, [
            'credential_id' => 'cred-revoked-1',
            'public_key' => 'public-key-revoked-1',
        ]);
        $credentialDomain->revoke($user, 'cred-revoked-1');

        $issue = $challengeFlow->issueAssertionChallenge($user, browserSupported: true);

        $result = $challengeFlow->verifyAssertion(
            challengeId: $issue->challengeId,
            clientChallenge: (string) $issue->challenge,
            credentialId: 'cred-revoked-1',
        );

        $this->assertSame(WebAuthnAssertionResult::CREDENTIAL_REVOKED, $result->status);
        $this->assertTrue($result->canFallbackToPassword);
    }

    public function test_expired_challenge_is_invalid_and_preserves_password_compatibility(): void
    {
        Carbon::setTestNow(now());

        $user = User::query()->create([
            'name' => 'Admin Expired Challenge',
            'username' => 'admin-expired-challenge',
            'password' => Hash::make('password-expired'),
        ]);

        $credentialDomain = app(WebAuthnCredentialDomain::class);
        $challengeFlow = app(WebAuthnChallengeFlow::class);

        $credentialDomain->enroll($user, [
            'credential_id' => 'cred-expired-1',
            'public_key' => 'public-key-expired-1',
        ]);

        $issue = $challengeFlow->issueAssertionChallenge($user, browserSupported: true);

        Carbon::setTestNow(now()->addMinutes(6));

        $result = $challengeFlow->verifyAssertion(
            challengeId: $issue->challengeId,
            clientChallenge: (string) $issue->challenge,
            credentialId: 'cred-expired-1',
        );

        $this->assertSame(WebAuthnAssertionResult::INVALID_CHALLENGE, $result->status);
        $this->assertTrue($result->canFallbackToPassword);
        $this->assertTrue(Hash::check('password-expired', $user->password));

        Carbon::setTestNow();
    }

    public function test_malformed_assertion_payload_is_rejected_even_when_challenge_and_credential_match(): void
    {
        $keyPair = $this->test_key_pair();

        $user = User::query()->create([
            'name' => 'Admin Malformed Assertion',
            'username' => 'admin-malformed-assertion',
            'password' => Hash::make('password-valid'),
        ]);

        $credentialDomain = app(WebAuthnCredentialDomain::class);
        $challengeFlow = app(WebAuthnChallengeFlow::class);

        $credentialDomain->enroll($user, [
            'credential_id' => 'cred-malformed-1',
            'public_key' => $keyPair['public_key'],
            'sign_count' => 4,
        ]);

        $issue = $challengeFlow->issueAssertionChallenge($user, browserSupported: true);

        $result = $challengeFlow->verifyAssertion(
            challengeId: $issue->challengeId,
            clientChallenge: (string) $issue->challenge,
            credentialId: 'cred-malformed-1',
            payload: [
                'credential_id' => 'cred-malformed-1',
                'raw_id' => 'cred-malformed-1',
            ],
        );

        $this->assertSame(WebAuthnAssertionResult::INVALID_CHALLENGE, $result->status);
        $this->assertTrue($result->canFallbackToPassword);
    }

    public function test_forged_signature_is_rejected_even_when_assertion_structure_matches(): void
    {
        $keyPair = $this->test_key_pair();

        $user = User::query()->create([
            'name' => 'Admin Forged Signature',
            'username' => 'admin-forged-signature',
            'password' => Hash::make('password-valid'),
        ]);

        $credentialDomain = app(WebAuthnCredentialDomain::class);
        $challengeFlow = app(WebAuthnChallengeFlow::class);

        $credentialDomain->enroll($user, [
            'credential_id' => 'cred-forged-signature-1',
            'public_key' => $keyPair['public_key'],
            'sign_count' => 4,
        ]);

        $issue = $challengeFlow->issueAssertionChallenge($user, browserSupported: true);

        $payload = $this->browserAssertionPayload(
            credentialId: 'cred-forged-signature-1',
            challenge: (string) $issue->challenge,
            signCount: 5,
            privateKey: $keyPair['private_key'],
        );

        $signature = $this->fromBase64Url((string) $payload['signature']);
        $this->assertNotNull($signature);
        $this->assertNotSame('', $signature);

        $signature[strlen($signature) - 1] = chr(ord($signature[strlen($signature) - 1]) ^ 1);
        $payload['signature'] = $this->toBase64Url($signature);

        $result = $challengeFlow->verifyAssertion(
            challengeId: $issue->challengeId,
            clientChallenge: (string) $issue->challenge,
            credentialId: 'cred-forged-signature-1',
            payload: $payload,
        );

        $this->assertSame(WebAuthnAssertionResult::INVALID_CHALLENGE, $result->status);
        $this->assertTrue($result->canFallbackToPassword);
    }

    /**
     * @return array<string, string|int>
     */
    private function browserAssertionPayload(string $credentialId, string $challenge, int $signCount, string $privateKey): array
    {
        $rpId = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $rpIdHash = hash('sha256', (string) $rpId, true);
        $flags = chr(0x01);
        $signCountBytes = pack('N', $signCount);
        $authenticatorData = $rpIdHash.$flags.$signCountBytes;

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

    private function fromBase64Url(string $value): ?string
    {
        $normalized = str_replace(['-', '_'], ['+', '/'], trim($value));
        $padding = strlen($normalized) % 4;

        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);

        return is_string($decoded) ? $decoded : null;
    }

    private function toBase64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
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
