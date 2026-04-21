<?php

namespace App\Filament\Pages\Auth;

use App\Contracts\Auth\WebAuthnChallengeFlow;
use App\Contracts\Auth\WebAuthnCredentialDomain;
use App\Contracts\SiteSettingsAccessor;
use App\Models\User;
use App\Support\Auth\WebAuthn\WebAuthnAssertionResult;
use App\Support\Auth\WebAuthn\WebAuthnChallengeIssueResult;
use App\Support\Security\EndpointProtectionPolicy;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Models\Contracts\FilamentUser;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    private const REMEMBERED_USERNAMES_COOKIE = 'admin_remembered_usernames';

    public array $rememberedUsernames = [];

    public ?string $pendingPasskeyUsername = null;

    public ?string $pendingPasskeyChallengeId = null;

    public ?string $pendingPasskeyChallenge = null;

    /**
     * @var array<int, string>
     */
    public array $pendingPasskeyAllowCredentialIds = [];

    public ?string $passkeyStatus = null;

    public ?string $passkeyMessage = null;

    public bool $passkeyCanFallbackToPassword = true;

    public function mount(): void
    {
        parent::mount();

        $this->rememberedUsernames = $this->loadRememberedUsernamesFromCookie();
        $this->data['remember_username'] = false;
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(
                EndpointProtectionPolicy::adminLoginAttempts(),
                EndpointProtectionPolicy::adminLoginDecaySeconds(),
            );
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();
        $user = $this->getUserFromFormData($data);

        if (! $user) {
            $this->throwUsernameValidationException();
        }

        if (! Hash::check($data['password'], $user->password)) {
            $this->throwPasswordValidationException();
        }

        Filament::auth()->login($user, $data['remember'] ?? false);

        if (
            ($user instanceof FilamentUser) &&
            (! $user->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            Filament::auth()->logout();

            $this->throwUsernameValidationException('Akun ini tidak memiliki akses ke panel admin.');
        }

        $this->clearRateLimiter();
        $this->persistRememberedUsernameOnDevice((string) $user->username, (bool) ($data['remember_username'] ?? false));

        session()->regenerate();

        return app(LoginResponse::class);
    }

    /**
     * @return array{status: string, challengeId: ?string, challenge: ?string, allowCredentialIds: array<int, string>, canFallbackToPassword: bool}
     */
    public function beginPasskeyLogin(?bool $browserSupported = null): array
    {
        $data = $this->data ?? [];
        $user = $this->getUserFromFormData($data);

        if (! $user) {
            $this->setPasskeyFeedback(
                status: 'unenrolled',
                message: 'Username belum dikenali atau belum memiliki passkey. Gunakan login password untuk melanjutkan.',
                canFallbackToPassword: true,
            );

            return $this->passkeyPayload();
        }

        $credentials = app(WebAuthnCredentialDomain::class)->listForUser($user, includeRevoked: true);
        $hasActivePasskey = $credentials->contains(fn ($credential): bool => $credential->revoked_at === null);

        if (! $hasActivePasskey && $credentials->isNotEmpty()) {
            $this->setPasskeyFeedback(
                status: WebAuthnAssertionResult::CREDENTIAL_REVOKED,
                message: 'Passkey untuk akun ini sudah dinonaktifkan. Gunakan login password lalu daftarkan passkey baru.',
                canFallbackToPassword: true,
            );

            return $this->passkeyPayload();
        }

        if ($credentials->isEmpty()) {
            $this->setPasskeyFeedback(
                status: 'unenrolled',
                message: 'Akun ini belum memiliki passkey aktif. Login dengan password lalu daftarkan passkey dari menu akun.',
                canFallbackToPassword: true,
            );

            return $this->passkeyPayload();
        }

        $allowCredentialIds = $credentials
            ->filter(fn ($credential): bool => $credential->revoked_at === null)
            ->pluck('credential_id')
            ->map(fn ($credentialId): string => trim((string) $credentialId))
            ->filter()
            ->values()
            ->all();

        $issue = app(WebAuthnChallengeFlow::class)->issueAssertionChallenge(
            user: $user,
            browserSupported: $browserSupported ?? true,
            context: ['origin' => 'admin_login'],
        );

        $this->pendingPasskeyUsername = (string) $user->username;
        $this->pendingPasskeyChallengeId = $issue->challengeId;
        $this->pendingPasskeyChallenge = $issue->challenge;
        $this->pendingPasskeyAllowCredentialIds = $allowCredentialIds;

        if ($issue->status === WebAuthnChallengeIssueResult::ISSUED) {
            $this->setPasskeyFeedback(
                status: $issue->status,
                message: 'Challenge passkey dibuat. Lanjutkan verifikasi passkey pada perangkat Anda.',
                canFallbackToPassword: $issue->canFallbackToPassword,
            );

            return $this->passkeyPayload();
        }

        $this->setPasskeyFeedback(
            status: $issue->status,
            message: 'Browser tidak mendukung passkey. Gunakan login password untuk melanjutkan.',
            canFallbackToPassword: $issue->canFallbackToPassword,
        );

        return $this->passkeyPayload();
    }

    public function completePasskeyLogin(
        string $credentialId,
        ?int $signCount = null,
        ?string $challengeId = null,
        ?string $clientChallenge = null,
        array $assertionPayload = [],
    ): ?LoginResponse {
        try {
            $this->rateLimit(
                EndpointProtectionPolicy::adminLoginAttempts(),
                EndpointProtectionPolicy::adminLoginDecaySeconds(),
            );
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->data ?? [];
        $challengeId = trim((string) ($challengeId ?? $this->pendingPasskeyChallengeId ?? ''));
        $clientChallenge = trim((string) ($clientChallenge ?? $this->pendingPasskeyChallenge ?? ''));
        $credentialId = trim($credentialId);

        if ($challengeId === '' || $clientChallenge === '' || $credentialId === '') {
            $this->setPasskeyFeedback(
                status: WebAuthnAssertionResult::INVALID_CHALLENGE,
                message: 'Flow passkey belum lengkap. Mulai lagi passkey login, atau gunakan password.',
                canFallbackToPassword: true,
            );

            return null;
        }

        $payload = [];

        if ($signCount !== null) {
            $payload['sign_count'] = $signCount;
        }

        foreach (['credential_id', 'raw_id', 'client_data_json', 'authenticator_data', 'signature', 'user_handle'] as $key) {
            if (! array_key_exists($key, $assertionPayload)) {
                continue;
            }

            $value = $assertionPayload[$key];

            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);

            if ($trimmed === '') {
                continue;
            }

            $payload[$key] = $trimmed;
        }

        $payload['credential_id'] = trim($credentialId);

        $result = app(WebAuthnChallengeFlow::class)->verifyAssertion(
            challengeId: $challengeId,
            clientChallenge: $clientChallenge,
            credentialId: $credentialId,
            payload: $payload,
        );

        if (! $result->verified()) {
            $this->setPasskeyFeedback(
                status: $result->status,
                message: $this->passkeyFailureMessageForStatus($result->status),
                canFallbackToPassword: $result->canFallbackToPassword,
            );

            return null;
        }

        $user = $this->pendingPasskeyUsername
            ? User::query()->where('username', $this->pendingPasskeyUsername)->first()
            : null;

        if (! $user) {
            $this->setPasskeyFeedback(
                status: WebAuthnAssertionResult::CREDENTIAL_NOT_FOUND,
                message: 'Akun passkey tidak ditemukan. Gunakan login password.',
                canFallbackToPassword: true,
            );

            return null;
        }

        Filament::auth()->login($user, $data['remember'] ?? false);

        if (
            ($user instanceof FilamentUser) &&
            (! $user->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            Filament::auth()->logout();

            $this->throwUsernameValidationException('Akun ini tidak memiliki akses ke panel admin.');
        }

        $this->clearRateLimiter();
        $this->persistRememberedUsernameOnDevice((string) $user->username, (bool) ($data['remember_username'] ?? false));
        $this->resetPasskeyFlow();
        session()->regenerate();

        return app(LoginResponse::class);
    }

    public function authenticateWithPasskey(): ?LoginResponse
    {
        $credentialId = trim((string) data_get($this->data ?? [], 'passkey_credential_id'));
        $signCount = data_get($this->data ?? [], 'passkey_sign_count');

        return $this->completePasskeyLogin(
            credentialId: $credentialId,
            signCount: is_numeric($signCount) ? (int) $signCount : null,
            assertionPayload: [],
        );
    }

    public function cancelPasskeyLogin(): void
    {
        if (filled($this->pendingPasskeyChallengeId)) {
            app(WebAuthnChallengeFlow::class)->cancel((string) $this->pendingPasskeyChallengeId);
        }

        $this->setPasskeyFeedback(
            status: WebAuthnAssertionResult::CEREMONY_CANCELLED,
            message: 'Verifikasi passkey dibatalkan. Anda bisa lanjut login dengan password.',
            canFallbackToPassword: true,
        );
    }

    public function clearRememberedUsernames(): void
    {
        $this->rememberedUsernames = [];
        Cookie::queue(Cookie::forget(self::REMEMBERED_USERNAMES_COOKIE));
        $this->data['remembered_username'] = null;
    }

    public function dismissPasskeyState(): void
    {
        if (filled($this->pendingPasskeyChallengeId)) {
            app(WebAuthnChallengeFlow::class)->cancel((string) $this->pendingPasskeyChallengeId);
        }

        $this->resetPasskeyFlow();
        $this->passkeyStatus = null;
        $this->passkeyMessage = null;
        $this->passkeyCanFallbackToPassword = true;
    }

    protected function getRateLimitKey($method, $component = null)
    {
        $method ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, limit: 2)[1]['function'];
        $component ??= static::class;

        return EndpointProtectionPolicy::adminLoginRateLimitKey(
            username: (string) data_get($this->data ?? [], 'username'),
            ip: (string) request()->ip(),
            component: (string) $component,
            method: (string) $method,
        );
    }

    protected function throwFailureValidationException(): never
    {
        $this->throwUsernameValidationException();
    }

    protected function throwUsernameValidationException(
        string $message = 'Username tidak ditemukan.'
    ): never {
        throw ValidationException::withMessages([
            'data.username' => $message,
        ]);
    }

    protected function throwPasswordValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.password' => 'Password yang Anda masukkan salah.',
        ]);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Username')
            ->placeholder('Masukkan username admin')
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('remembered_username')
                    ->label('Username tersimpan')
                    ->options(fn (): array => collect($this->rememberedUsernames)
                        ->mapWithKeys(fn (string $username): array => [$username => $username])
                        ->all())
                    ->placeholder('Pilih username')
                    ->visible(fn (): bool => count($this->rememberedUsernames) > 0)
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        if (filled($state)) {
                            $set('username', $state);
                        }
                    }),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                Checkbox::make('remember_username')
                    ->label('Ingat username di perangkat ini'),
                Hidden::make('passkey_credential_id'),
                Hidden::make('passkey_sign_count'),
                $this->getRememberFormComponent(),
            ]);
    }

    public function getTitle(): string|Htmlable
    {
        $siteName = app(SiteSettingsAccessor::class)->siteName();

        return trim($siteName) !== '' ? $siteName.' Admin' : 'Admin';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Selamat Datang';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Masuk ke Panel Admin SMA AFBS.';
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'username' => $data['username'],
            'password' => $data['password'],
        ];
    }

    protected function getUserFromFormData(array $data): ?User
    {
        $username = trim((string) data_get($data, 'username'));

        if ($username === '') {
            return null;
        }

        $user = User::query()
            ->where('username', $username)
            ->first();

        if ($user) {
            $user->loadMissing('roles');
        }

        return $user;
    }

    private function setPasskeyFeedback(string $status, string $message, bool $canFallbackToPassword): void
    {
        $this->passkeyStatus = $status;
        $this->passkeyMessage = $message;
        $this->passkeyCanFallbackToPassword = $canFallbackToPassword;

        if ($status !== WebAuthnChallengeIssueResult::ISSUED) {
            $this->resetPasskeyFlow();
        }
    }

    private function resetPasskeyFlow(): void
    {
        $this->pendingPasskeyUsername = null;
        $this->pendingPasskeyChallengeId = null;
        $this->pendingPasskeyChallenge = null;
        $this->pendingPasskeyAllowCredentialIds = [];
        $this->data['passkey_credential_id'] = null;
        $this->data['passkey_sign_count'] = null;
    }

    /**
     * @return array{status: string, challengeId: ?string, challenge: ?string, allowCredentialIds: array<int, string>, canFallbackToPassword: bool}
     */
    private function passkeyPayload(): array
    {
        return [
            'status' => (string) ($this->passkeyStatus ?? ''),
            'challengeId' => $this->pendingPasskeyChallengeId,
            'challenge' => $this->pendingPasskeyChallenge,
            'allowCredentialIds' => $this->pendingPasskeyAllowCredentialIds,
            'canFallbackToPassword' => $this->passkeyCanFallbackToPassword,
        ];
    }

    private function loadRememberedUsernamesFromCookie(): array
    {
        $payload = request()->cookie(self::REMEMBERED_USERNAMES_COOKIE);

        if (! is_string($payload) || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->map(fn ($username): string => trim((string) $username))
            ->filter(fn (string $username): bool => $username !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $username) === 1)
            ->unique()
            ->take(5)
            ->values()
            ->all();
    }

    private function persistRememberedUsernameOnDevice(string $username, bool $optIn): void
    {
        $normalizedUsername = trim($username);
        $remembered = collect($this->rememberedUsernames)
            ->map(fn ($item): string => trim((string) $item))
            ->filter()
            ->reject(fn (string $item): bool => $item === $normalizedUsername)
            ->values();

        if ($optIn && $normalizedUsername !== '') {
            $remembered->prepend($normalizedUsername);
        }

        $remembered = $remembered
            ->filter(fn (string $item): bool => preg_match('/^[A-Za-z0-9._-]+$/', $item) === 1)
            ->unique()
            ->take(5)
            ->values();

        $this->rememberedUsernames = $remembered->all();

        if ($remembered->isEmpty()) {
            Cookie::queue(Cookie::forget(self::REMEMBERED_USERNAMES_COOKIE));

            return;
        }

        Cookie::queue(cookie(
            self::REMEMBERED_USERNAMES_COOKIE,
            json_encode($remembered->all()),
            60 * 24 * 180,
        ));
    }

    private function passkeyFailureMessageForStatus(string $status): string
    {
        return match ($status) {
            WebAuthnAssertionResult::UNSUPPORTED_BROWSER => 'Browser tidak mendukung passkey. Gunakan login password.',
            WebAuthnAssertionResult::CEREMONY_CANCELLED => 'Verifikasi passkey dibatalkan. Gunakan login password bila diperlukan.',
            WebAuthnAssertionResult::INVALID_CHALLENGE => 'Sesi verifikasi passkey sudah tidak valid atau kedaluwarsa. Mulai ulang passkey login, atau gunakan password.',
            WebAuthnAssertionResult::CREDENTIAL_REVOKED => 'Passkey ini sudah dinonaktifkan. Login dengan password lalu daftarkan passkey baru.',
            WebAuthnAssertionResult::CREDENTIAL_NOT_FOUND => 'Passkey tidak ditemukan untuk akun ini. Gunakan login password.',
            default => 'Passkey gagal diverifikasi. Anda tetap bisa login menggunakan password.',
        };
    }
}
