<?php

namespace App\Filament\Pages;

use App\Contracts\Auth\WebAuthnCredentialDomain;
use App\Models\User;
use App\Models\WebAuthnCredential;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ManagePasskeys extends Page
{
    protected static ?string $title = 'Passkey Akun';

    protected static ?string $slug = 'account/passkeys';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected string $view = 'filament.pages.manage-passkeys';

    public ?string $label = null;

    public ?string $credentialId = null;

    public ?string $publicKey = null;

    public ?string $transports = null;

    public ?int $signCount = null;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->user() instanceof User;
    }

    public function enrollPasskey(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $validated = $this->validate([
            'label' => ['nullable', 'string', 'max:191'],
            'credentialId' => ['required', 'string', 'max:191'],
            'publicKey' => ['required', 'string'],
            'transports' => ['nullable', 'string', 'max:255'],
            'signCount' => ['nullable', 'integer', 'min:0'],
        ], attributes: [
            'credentialId' => 'credential id',
            'publicKey' => 'public key',
            'signCount' => 'sign count',
        ]);

        try {
            app(WebAuthnCredentialDomain::class)->enroll($user, [
                'label' => $validated['label'] ?? null,
                'credential_id' => trim((string) $validated['credentialId']),
                'public_key' => trim((string) $validated['publicKey']),
                'transports' => collect(explode(',', (string) ($validated['transports'] ?? '')))
                    ->map(fn (string $transport): string => trim($transport))
                    ->filter()
                    ->values()
                    ->all(),
                'sign_count' => $validated['signCount'] ?? 0,
            ]);
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages([
                'credentialId' => $exception->getMessage(),
            ]);
        }

        $this->reset(['label', 'credentialId', 'publicKey', 'transports', 'signCount']);

        Notification::make()
            ->title('Passkey berhasil ditambahkan')
            ->body('Perangkat ini sekarang dapat digunakan untuk login passkey di /admin/login.')
            ->success()
            ->send();
    }

    public function revokePasskey(string $credentialId): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $revoked = app(WebAuthnCredentialDomain::class)->revoke($user, trim($credentialId));

        Notification::make()
            ->title($revoked ? 'Passkey dinonaktifkan' : 'Passkey tidak ditemukan')
            ->body($revoked
                ? 'Passkey tersebut tidak bisa lagi dipakai login. Gunakan password atau daftarkan passkey baru.'
                : 'Pastikan passkey yang dipilih masih aktif di akun ini.')
            ->{$revoked ? 'success' : 'warning'}()
            ->send();
    }

    /**
     * @return Collection<int, WebAuthnCredential>
     */
    public function getCredentialsProperty(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        return app(WebAuthnCredentialDomain::class)->listForUser($user, includeRevoked: true);
    }
}
