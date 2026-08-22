<?php

namespace Tests\Feature;

use App\Contracts\Auth\WebAuthnChallengeFlow;
use App\Filament\Pages\Auth\Login;
use App\Support\Auth\WebAuthn\WebAuthnChallengeIssueResult;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class AdminPasskeyFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->runWebAuthnMigrations();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_disabled_feature_keeps_password_fallback_available(): void
    {
        config(['webauthn.enabled' => false]);

        Livewire::test(Login::class)
            ->call('beginPasskeyLogin', true)
            ->assertSet('passkeyStatus', WebAuthnChallengeIssueResult::DISABLED)
            ->assertSet('passkeyCanFallbackToPassword', true);
    }

    public function test_unsupported_browser_gets_clear_password_guidance(): void
    {
        config(['webauthn.enabled' => true]);

        Livewire::test(Login::class)
            ->call('beginPasskeyLogin', false)
            ->assertSet('passkeyStatus', WebAuthnChallengeIssueResult::UNSUPPORTED_BROWSER)
            ->assertSet('passkeyCanFallbackToPassword', true);
    }

    public function test_invalid_payload_never_authenticates_a_user(): void
    {
        config(['webauthn.enabled' => true]);

        Livewire::test(Login::class)
            ->call('completePasskeyLogin', 'unknown', null, [])
            ->assertSet('passkeyStatus', 'invalid_challenge');

        $this->assertGuest();
    }

    public function test_starting_a_new_passkey_login_cancels_the_previous_pending_challenge(): void
    {
        config(['webauthn.enabled' => true]);

        $flow = Mockery::mock(WebAuthnChallengeFlow::class);
        $flow->shouldReceive('issueDiscoverableAssertionChallenge')->twice()->andReturn(
            new WebAuthnChallengeIssueResult(
                WebAuthnChallengeIssueResult::ISSUED,
                '11111111-1111-4111-8111-111111111111',
                'challenge-one',
                true,
                ['challenge' => 'Y2hhbGxlbmdlLTE'],
            ),
            new WebAuthnChallengeIssueResult(
                WebAuthnChallengeIssueResult::ISSUED,
                '22222222-2222-4222-8222-222222222222',
                'challenge-two',
                true,
                ['challenge' => 'Y2hhbGxlbmdlLTI'],
            ),
        );
        $flow->shouldReceive('cancel')
            ->once()
            ->with('11111111-1111-4111-8111-111111111111', 'superseded_by_new_challenge');
        $this->app->instance(WebAuthnChallengeFlow::class, $flow);

        Livewire::test(Login::class)
            ->call('beginPasskeyLogin', true)
            ->call('beginPasskeyLogin', true)
            ->assertSet('pendingPasskeyChallengeId', '22222222-2222-4222-8222-222222222222');
    }

    public function test_client_credential_manager_failure_only_cancels_the_session_pending_challenge(): void
    {
        config(['webauthn.enabled' => true]);

        $flow = Mockery::mock(WebAuthnChallengeFlow::class);
        $flow->shouldReceive('issueDiscoverableAssertionChallenge')->once()->andReturn(
            new WebAuthnChallengeIssueResult(
                WebAuthnChallengeIssueResult::ISSUED,
                '33333333-3333-4333-8333-333333333333',
                'challenge',
                true,
                ['challenge' => 'Y2hhbGxlbmdl'],
            ),
        );
        $flow->shouldReceive('cancel')
            ->once()
            ->with('33333333-3333-4333-8333-333333333333', 'client_credential_manager_unknown');
        $this->app->instance(WebAuthnChallengeFlow::class, $flow);

        Livewire::test(Login::class)
            ->call('beginPasskeyLogin', true)
            ->call('reportPasskeyClientFailure', 'not-the-pending-challenge', 'client_credential_manager_unknown')
            ->assertSet('pendingPasskeyChallengeId', '33333333-3333-4333-8333-333333333333')
            ->call('reportPasskeyClientFailure', '33333333-3333-4333-8333-333333333333', 'client_credential_manager_unknown')
            ->assertSet('pendingPasskeyChallengeId', null);
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
