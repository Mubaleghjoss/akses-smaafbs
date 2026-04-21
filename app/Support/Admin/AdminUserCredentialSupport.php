<?php

namespace App\Support\Admin;

use App\Models\User;
use App\Support\GuruTendik\GuruTendikAccountProvisioner;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class AdminUserCredentialSupport
{
    /**
     * @return array{user: User, name: string, username: string, password: string, created: bool}
     */
    public static function resetDefaultPassword(User $user): array
    {
        $user->loadMissing('guruTendik');

        $seedName = $user->guruTendik?->nama ?: $user->name;
        $seedId = $user->guruTendik?->getKey() ?: $user->getKey();
        $password = app(GuruTendikAccountProvisioner::class)->generateDefaultPassword($seedName, $seedId);

        $user->updateToDefaultPassword($password);

        return [
            'user' => $user->fresh(),
            'name' => (string) ($user->guruTendik?->nama ?: $user->name),
            'username' => (string) $user->username,
            'password' => $password,
            'created' => false,
        ];
    }

    /**
     * @param  Collection<int, User>  $records
     * @return array{reset:int, skipped:int, credentials:array<int, array{name:string, username:string, password:string, created:bool}>}
     */
    public static function resetDefaultPasswordsForUsers(Collection $records): array
    {
        $summary = [
            'reset' => 0,
            'skipped' => 0,
            'credentials' => [],
        ];

        foreach ($records as $record) {
            if (! $record instanceof User) {
                $summary['skipped']++;

                continue;
            }

            if ((int) $record->getKey() === (int) auth()->id()) {
                $summary['skipped']++;

                continue;
            }

            $result = self::resetDefaultPassword($record);
            $summary['reset']++;
            $summary['credentials'][] = [
                'name' => $result['name'],
                'username' => $result['username'],
                'password' => $result['password'],
                'created' => false,
            ];
        }

        return $summary;
    }

    public static function credentialsAsCopyableHtml(array $credentials): string
    {
        $formalText = e(self::credentialsAsFormalText($credentials));
        $quickText = e(self::credentialsAsQuickText($credentials));

        return <<<HTML
            <div class="space-y-3 text-xs">
                <p class="font-medium">Kredensial baru sudah dibuat. Salin salah satu format di bawah ini:</p>

                <section class="js-copy-credentials-template space-y-2 rounded-lg border border-amber-200 bg-amber-50/40 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-semibold text-amber-900">Template Formal</p>
                        <button
                            type="button"
                            class="js-copy-credentials-btn inline-flex min-h-10 items-center justify-center rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 transition hover:bg-amber-100"
                        >
                            Salin Template Formal
                        </button>
                    </div>
                    <pre class="js-copy-credentials-text max-h-52 overflow-auto whitespace-pre-wrap rounded-lg border border-amber-200 bg-white p-3 font-mono text-xs leading-5 text-gray-900">{$formalText}</pre>
                </section>

                <section class="js-copy-credentials-template space-y-2 rounded-lg border border-gray-200 bg-gray-50/60 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-semibold text-gray-900">Template Singkat</p>
                        <button
                            type="button"
                            class="js-copy-credentials-btn inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-100"
                        >
                            Salin Template Singkat
                        </button>
                    </div>
                    <pre class="js-copy-credentials-text max-h-52 overflow-auto whitespace-pre-wrap rounded-lg border border-gray-300 bg-white p-3 font-mono text-xs leading-5 text-gray-900">{$quickText}</pre>
                </section>
            </div>
        HTML;
    }

    public static function credentialsAsFormalText(array $credentials): string
    {
        if (blank($credentials)) {
            return 'Tidak ada kredensial akun yang diproses.';
        }

        $lines = [
            'Berikut kami sampaikan kredensial login akun sekolah:',
            '',
            ...self::credentialRowsAsLines($credentials, true),
            'Mohon segera login dan ganti password default demi keamanan akun.',
        ];

        return trim(implode(PHP_EOL, $lines));
    }

    public static function credentialsAsQuickText(array $credentials): string
    {
        if (blank($credentials)) {
            return 'Tidak ada kredensial akun yang diproses.';
        }

        $lines = [
            'Kredensial login akun:',
            '',
            ...self::credentialRowsAsLines($credentials, false),
            'Silakan login lalu ganti password default.',
        ];

        return trim(implode(PHP_EOL, $lines));
    }

    /**
     * @param  array<int, array<string, mixed>>  $credentials
     * @return array<int, string>
     */
    protected static function credentialRowsAsLines(array $credentials, bool $withStatus): array
    {
        $lines = [];

        foreach (array_values($credentials) as $index => $item) {
            $lines[] = ($index + 1).'. '.(string) Arr::get($item, 'name', '-');

            if ($withStatus) {
                $lines[] = '   Status: Reset default';
            }

            $lines[] = '   Username: '.(string) Arr::get($item, 'username', '-');
            $lines[] = '   Password: '.(string) Arr::get($item, 'password', '-');
            $lines[] = '';
        }

        return $lines;
    }
}
