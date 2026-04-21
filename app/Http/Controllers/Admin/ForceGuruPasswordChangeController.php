<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ForceGuruPasswordChangeController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isGuru()) {
            abort(403);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'redirect_to' => ['nullable', 'string'],
        ], [
            'password.confirmed' => 'Konfirmasi password baru tidak sama.',
        ], [
            'current_password' => 'password saat ini',
            'password' => 'password baru',
        ]);

        if (! Hash::check((string) $validated['current_password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Password saat ini tidak sesuai.',
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
        auth()->login($updatedUser);
        session()->put('password_hash_'.config('auth.defaults.guard', 'web'), $updatedUser->getAuthPassword());

        Notification::make()
            ->title('Password berhasil diperbarui.')
            ->body('Akun Anda sudah aktif penuh. Silakan lanjutkan ke dashboard.')
            ->success()
            ->send();

        $redirectTo = (string) ($validated['redirect_to'] ?? '');

        if (! str_starts_with($redirectTo, '/admin')) {
            $redirectTo = '/admin';
        }

        return redirect()->to($redirectTo);
    }
}
