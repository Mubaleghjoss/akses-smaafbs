<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ForceGuruPasswordChange extends Page
{
    protected static ?string $title = 'Ganti Password Pertama';

    protected static ?string $slug = 'force-guru-password-change';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected string $view = 'filament.pages.force-guru-password-change';

    public ?string $currentPassword = null;

    public ?string $password = null;

    public ?string $password_confirmation = null;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isGuru();
    }

    public function changePassword(): void
    {
        /** @var ?User $user */
        $user = auth()->user();

        if (! $user instanceof User || ! $user->isGuru()) {
            abort(403);
        }

        $validated = $this->validate([
            'currentPassword' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'password.confirmed' => 'Konfirmasi password baru tidak sama.',
        ], [
            'currentPassword' => 'password saat ini',
            'password' => 'password baru',
        ]);

        if (! Hash::check((string) $validated['currentPassword'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => 'Password saat ini tidak sesuai.',
            ]);
        }

        if (Hash::check((string) $validated['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Password baru harus berbeda dari password default saat ini.',
            ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
            'uses_default_password' => false,
        ])->save();

        $updatedUser = $user->fresh();
        Filament::auth()->login($updatedUser);
        session()->put('password_hash_'.config('auth.defaults.guard', 'web'), $updatedUser->getAuthPassword());

        $this->reset(['currentPassword', 'password', 'password_confirmation']);

        Notification::make()
            ->title('Password berhasil diperbarui.')
            ->body('Akun Anda sudah aktif penuh. Silakan lanjutkan ke dashboard.')
            ->success()
            ->send();

        $this->redirect(Filament::getHomeUrl(), navigate: true);
    }

    protected function getViewData(): array
    {
        return [
            'mustForceChange' => auth()->user() instanceof User && auth()->user()->shouldForceDefaultPasswordChange(),
        ];
    }
}
