<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Support\Auth\WebAuthn\WebAuthnChallengeIssueResult;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
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
