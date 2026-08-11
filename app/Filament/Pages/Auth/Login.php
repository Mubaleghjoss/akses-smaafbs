<?php

namespace App\Filament\Pages\Auth;

use App\Contracts\Auth\WebAuthnChallengeFlow;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Models\Contracts\FilamentUser;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    private const REMEMBERED_USERNAMES_COOKIE = 'admin_remembered_usernames';

    public array $rememberedUsernames = [];

    public ?string $pendingPasskeyChallengeId = null;

    public array $pendingPasskeyPublicKeyOptions = [];

    public ?string $passkeyStatus = null;

    public ?string $passkeyMessage = null;

    public bool $passkeyCanFallbackToPassword = true;

    public ?string $loginErrorMessage = null;

    public function mount(): void
    {
        parent::mount();

        $this->rememberedUsernames = $this->loadRememberedUsernamesFromCookie();
        $this->data['remember_username'] = false;
    }

    public function authenticate(): ?LoginResponse
    {
        $this->loginErrorMessage = null;

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

    /** @return array{status: string, challengeId: ?string, publicKeyOptions: array<string, mixed>, canFallbackToPassword: bool} */
    public function beginPasskeyLogin(?bool $browserSupported = null): array
    {
        try {
            $this->rateLimit(12, 60);
        } catch (TooManyRequestsException $exception) {
            $this->setPasskeyFeedback(
                status: 'rate_limited',
                message: 'Terlalu banyak percobaan pada sesi ini. Tunggu sebentar atau gunakan password.',
                canFallbackToPassword: true,
            );

            return $this->passkeyPayload();
        }

        $issue = app(WebAuthnChallengeFlow::class)->issueDiscoverableAssertionChallenge(
            browserSupported: $browserSupported ?? true,
            context: ['origin' => 'admin_login'],
        );

        $this->pendingPasskeyChallengeId = $issue->challengeId !== '' ? $issue->challengeId : null;
        $this->pendingPasskeyPublicKeyOptions = $issue->publicKeyOptions ?? [];

        $this->setPasskeyFeedback(
            status: $issue->status,
            message: match ($issue->status) {
                WebAuthnChallengeIssueResult::ISSUED => 'Pilih akun passkey lalu verifikasi dengan sidik jari, PIN, atau pengunci layar perangkat.',
                WebAuthnChallengeIssueResult::DISABLED => 'Login passkey sedang dinonaktifkan. Gunakan username dan password.',
                default => 'Browser ini belum mendukung passkey. Gunakan username dan password.',
            },
            canFallbackToPassword: true,
        );

        return $this->passkeyPayload();
    }

    public function completePasskeyLogin(
        string $credentialId,
        ?string $challengeId = null,
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

        $challengeId = trim((string) ($challengeId ?? $this->pendingPasskeyChallengeId ?? ''));
        $credentialId = trim($credentialId);

        if ($challengeId === '' || $credentialId === '') {
            $this->setPasskeyFeedback(
                status: WebAuthnAssertionResult::INVALID_CHALLENGE,
                message: 'Flow passkey belum lengkap. Mulai lagi passkey login, atau gunakan password.',
                canFallbackToPassword: true,
            );

            return null;
        }

        $payload = [];

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

        $payload = validator($payload, [
            'credential_id' => ['required', 'string', 'max:1024'],
            'raw_id' => ['nullable', 'string', 'max:2048'],
            'client_data_json' => ['required', 'string', 'max:65535'],
            'authenticator_data' => ['required', 'string', 'max:65535'],
            'signature' => ['required', 'string', 'max:65535'],
            'user_handle' => ['required', 'string', 'max:2048'],
        ])->validate();

        $result = app(WebAuthnChallengeFlow::class)->verifyDiscoverableAssertion(
            challengeId: $challengeId,
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

        $user = $result->user;

        if (! $user) {
            $this->setPasskeyFeedback(
                status: WebAuthnAssertionResult::CREDENTIAL_NOT_FOUND,
                message: 'Akun passkey tidak ditemukan. Gunakan login password.',
                canFallbackToPassword: true,
            );

            return null;
        }

        Filament::auth()->login($user, true);

        if (
            ($user instanceof FilamentUser) &&
            (! $user->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            Filament::auth()->logout();

            $this->throwUsernameValidationException('Akun ini tidak memiliki akses ke panel admin.');
        }

        $this->clearRateLimiter();
        $this->resetPasskeyFlow();
        session()->regenerate();

        return app(LoginResponse::class);
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
        $isPasskeyMethod = str_contains(strtolower((string) $method), 'passkey');

        return EndpointProtectionPolicy::adminLoginRateLimitKey(
            username: $isPasskeyMethod
                ? 'passkey-session-'.hash('sha256', (string) session()->getId())
                : (string) data_get($this->data ?? [], 'username'),
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
        $this->loginErrorMessage = $message === 'Akun ini tidak memiliki akses ke panel admin.'
            ? $message
            : 'Username atau password tidak sesuai. Periksa kembali lalu coba login.';

        throw ValidationException::withMessages([
            'data.username' => $message,
        ]);
    }

    protected function throwPasswordValidationException(): never
    {
        $this->loginErrorMessage = 'Username atau password tidak sesuai. Periksa kembali lalu coba login.';

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
                SchemaView::make('filament.components.auth.login-alert')
                    ->visible(fn (): bool => filled($this->loginErrorMessage)),
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
        $this->pendingPasskeyChallengeId = null;
        $this->pendingPasskeyPublicKeyOptions = [];
    }

    /**
     * @return array{status: string, challengeId: ?string, publicKeyOptions: array<string, mixed>, canFallbackToPassword: bool}
     */
    private function passkeyPayload(): array
    {
        return [
            'status' => (string) ($this->passkeyStatus ?? ''),
            'challengeId' => $this->pendingPasskeyChallengeId,
            'publicKeyOptions' => $this->pendingPasskeyPublicKeyOptions,
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
            WebAuthnAssertionResult::CREDENTIAL_NOT_FOUND => 'Passkey tidak dikenali. Login dengan password lalu buka Passkey & Sidik Jari.',
            WebAuthnChallengeIssueResult::DISABLED => 'Login passkey sedang dinonaktifkan. Gunakan username dan password.',
            WebAuthnAssertionResult::SIGN_COUNT_REGRESSION => 'Passkey ini perlu didaftarkan ulang demi keamanan. Gunakan password untuk masuk.',
            'legacy_credential' => 'Passkey lama perlu didaftarkan ulang. Login dengan password lalu buka Passkey & Sidik Jari.',
            'invalid_origin' => 'Alamat aplikasi tidak sesuai untuk passkey. Buka https://app.smaafbs.sch.id lalu coba lagi.',
            'invalid_signature', 'verification_failed' => 'Sidik jari/passkey tidak dapat diverifikasi. Silakan coba lagi atau gunakan password.',
            'rate_limited' => 'Terlalu banyak percobaan pada sesi ini. Tunggu sebentar atau gunakan password.',
            default => 'Passkey gagal diverifikasi. Anda tetap bisa login menggunakan password.',
        };
    }
}
