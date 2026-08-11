<?php

namespace App\Filament\Pages;

use App\Contracts\Auth\WebAuthnChallengeFlow;
use App\Contracts\Auth\WebAuthnCredentialDomain;
use App\Models\User;
use App\Models\WebAuthnCredential;
use App\Support\Auth\WebAuthn\WebAuthnCeremonyException;
use App\Support\Auth\WebAuthn\WebAuthnChallengeIssueResult;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ManagePasskeys extends Page
{
    protected static ?string $title = 'Passkey & Sidik Jari';

    protected static ?string $slug = 'account/passkeys';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected string $view = 'filament.pages.manage-passkeys';

    public ?string $deviceName = null;

    public ?string $registrationStatus = null;

    public ?string $registrationMessage = null;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->user() instanceof User;
    }

    /** @return array{status: string, challengeId: ?string, publicKeyOptions: array<string, mixed>, message: string} */
    public function beginPasskeyRegistration(?bool $browserSupported = null): array
    {
        $user = $this->authenticatedUser();
        $this->validate(['deviceName' => ['nullable', 'string', 'max:100']]);

        $key = 'webauthn:register:'.hash('sha256', $user->getKey().'|'.session()->getId());

        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages([
                'deviceName' => 'Terlalu banyak percobaan. Tunggu '.RateLimiter::availableIn($key).' detik lalu coba lagi.',
            ]);
        }

        RateLimiter::hit($key, 60);

        try {
            $issue = app(WebAuthnChallengeFlow::class)->issueRegistrationChallenge(
                user: $user,
                browserSupported: $browserSupported ?? true,
                context: ['origin' => 'account_passkeys'],
            );
        } catch (WebAuthnCeremonyException $exception) {
            $this->registrationStatus = $exception->status;
            $this->registrationMessage = $exception->getMessage();

            return $this->registrationPayload($exception->status, null, []);
        }

        $message = match ($issue->status) {
            WebAuthnChallengeIssueResult::ISSUED => 'Ikuti permintaan sidik jari, PIN, atau pengunci layar yang muncul pada perangkat.',
            WebAuthnChallengeIssueResult::DISABLED => 'Fitur passkey sedang dinonaktifkan. Login password tetap dapat digunakan.',
            default => 'Browser atau perangkat ini belum mendukung pendaftaran passkey.',
        };

        $this->registrationStatus = $issue->status;
        $this->registrationMessage = $message;

        return $this->registrationPayload(
            $issue->status,
            $issue->challengeId !== '' ? $issue->challengeId : null,
            $issue->publicKeyOptions ?? [],
        );
    }

    public function completePasskeyRegistration(string $challengeId, array $attestationPayload): void
    {
        $user = $this->authenticatedUser();

        $validated = validator([
            'challenge_id' => $challengeId,
            'client_data_json' => $attestationPayload['client_data_json'] ?? null,
            'attestation_object' => $attestationPayload['attestation_object'] ?? null,
            'transports' => $attestationPayload['transports'] ?? [],
        ], [
            'challenge_id' => ['required', 'uuid'],
            'client_data_json' => ['required', 'string', 'max:65535'],
            'attestation_object' => ['required', 'string', 'max:262144'],
            'transports' => ['array', 'max:8'],
            'transports.*' => ['string', 'max:30'],
        ])->validate();

        try {
            $credential = app(WebAuthnChallengeFlow::class)->verifyRegistration(
                user: $user,
                challengeId: $validated['challenge_id'],
                payload: $validated,
                deviceName: $this->deviceName,
            );
        } catch (WebAuthnCeremonyException $exception) {
            $this->registrationStatus = $exception->status;
            $this->registrationMessage = $exception->getMessage();

            throw ValidationException::withMessages(['deviceName' => $exception->getMessage()]);
        }

        $this->reset('deviceName');
        $this->registrationStatus = 'completed';
        $this->registrationMessage = 'Passkey berhasil diaktifkan pada '.($credential->device_name ?: 'perangkat ini').'.';

        Notification::make()
            ->title('Passkey berhasil diaktifkan')
            ->body('Mulai sekarang akun ini dapat login tanpa mengetik username melalui sidik jari, PIN, atau pengunci layar.')
            ->success()
            ->send();
    }

    public function revokePasskey(string $credentialId): void
    {
        $user = $this->authenticatedUser();
        $revoked = app(WebAuthnCredentialDomain::class)->revoke($user, trim($credentialId));

        Notification::make()
            ->title($revoked ? 'Perangkat dinonaktifkan' : 'Passkey tidak ditemukan')
            ->body($revoked
                ? 'Riwayat tetap disimpan, tetapi perangkat tersebut tidak dapat dipakai login lagi.'
                : 'Muat ulang halaman lalu coba kembali.')
            ->{$revoked ? 'success' : 'warning'}()
            ->send();
    }

    /** @return Collection<int, WebAuthnCredential> */
    public function getCredentialsProperty(): Collection
    {
        return app(WebAuthnCredentialDomain::class)->listForUser($this->authenticatedUser(), includeRevoked: true);
    }

    /** @return Collection<int, WebAuthnCredential> */
    public function getActiveCredentialsProperty(): Collection
    {
        return $this->credentials->filter(fn (WebAuthnCredential $credential): bool => $credential->isVerifiedPasskey() && $credential->revoked_at === null);
    }

    /** @return Collection<int, WebAuthnCredential> */
    public function getHistoryCredentialsProperty(): Collection
    {
        return $this->credentials->reject(fn (WebAuthnCredential $credential): bool => $credential->isVerifiedPasskey() && $credential->revoked_at === null);
    }

    public function getPasskeyStatusProperty(): string
    {
        if ($this->activeCredentials->isNotEmpty()) {
            return 'Aktif';
        }

        return $this->historyCredentials->contains(fn (WebAuthnCredential $credential): bool => $credential->isLegacy())
            ? 'Perlu Daftar Ulang'
            : 'Belum Aktif';
    }

    private function authenticatedUser(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /** @return array{status: string, challengeId: ?string, publicKeyOptions: array<string, mixed>, message: string} */
    private function registrationPayload(string $status, ?string $challengeId, array $options): array
    {
        return [
            'status' => $status,
            'challengeId' => $challengeId,
            'publicKeyOptions' => $options,
            'message' => (string) $this->registrationMessage,
        ];
    }
}
