<?php

namespace App\Support\GuruTendik;

use App\Models\GuruTendik;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class GuruTendikAccountProvisioner
{
    /**
     * @return array{user: User, username: string, password: string, created: bool}
     */
    public function provisionOrResetForGuru(GuruTendik $guruTendik): array
    {
        return DB::transaction(function () use ($guruTendik): array {
            $guruTendik->loadMissing('userAccount');

            if ($guruTendik->userAccount instanceof User) {
                return $this->resetDefaultPassword($guruTendik->userAccount, $guruTendik);
            }

            $username = $this->generateUniqueUsernameFromName($guruTendik->nama);
            $password = $this->generateDefaultPassword($guruTendik->nama, $guruTendik->getKey());

            $user = User::query()->create([
                'name' => $guruTendik->nama,
                'username' => $username,
                'password' => $password,
                'guru_tendik_id' => $guruTendik->getKey(),
                'uses_default_password' => true,
                'default_password_reset_at' => now(),
                'default_password_changed_at' => null,
            ]);

            Role::findOrCreate('guru', 'web');
            $user->assignRole('guru');

            return [
                'user' => $user,
                'username' => $username,
                'password' => $password,
                'created' => true,
            ];
        });
    }

    /**
     * @return array{user: User, username: string, password: string, created: bool}
     */
    public function resetDefaultPassword(User $user, ?GuruTendik $guruTendik = null): array
    {
        $guruTendik ??= $user->guruTendik;

        $seedName = $guruTendik?->nama ?: $user->name;
        $seedId = $guruTendik?->getKey() ?: $user->getKey();
        $password = $this->generateDefaultPassword($seedName, $seedId);

        $user->updateToDefaultPassword($password);

        if (! $user->hasRole('guru')) {
            Role::findOrCreate('guru', 'web');
            $user->assignRole('guru');
        }

        return [
            'user' => $user,
            'username' => (string) $user->username,
            'password' => $password,
            'created' => false,
        ];
    }

    /**
     * @param  Collection<int, GuruTendik>  $records
     * @return array{created: int, reset: int, skipped: int, credentials: array<int, array{guru_tendik: string, username: string, password: string, created: bool}>}
     */
    public function provisionForCollection(Collection $records): array
    {
        $summary = [
            'created' => 0,
            'reset' => 0,
            'skipped' => 0,
            'credentials' => [],
        ];

        foreach ($records as $record) {
            if (! $record instanceof GuruTendik) {
                $summary['skipped']++;

                continue;
            }

            $result = $this->provisionOrResetForGuru($record);
            $summary[$result['created'] ? 'created' : 'reset']++;
            $summary['credentials'][] = [
                'guru_tendik' => $record->nama,
                'username' => $result['username'],
                'password' => $result['password'],
                'created' => $result['created'],
            ];
        }

        return $summary;
    }

    public function generateUniqueUsernameFromName(string $name, ?int $ignoreUserId = null): string
    {
        $base = $this->usernameBaseFromName($name);

        $candidate = Str::substr($base, 0, 50);
        $suffix = 2;

        while ($this->usernameExists($candidate, $ignoreUserId)) {
            $suffixText = '-'.$suffix;
            $candidate = Str::substr($base, 0, 50 - strlen($suffixText)).$suffixText;
            $suffix++;
        }

        return $candidate;
    }

    public function usernameBaseFromName(string $name): string
    {
        $normalized = collect(preg_split('/\s+/u', trim($name)) ?: [])
            ->map(fn ($part): string => Str::of($part)->ascii()->lower()->replaceMatches('/[^a-z0-9]/', '')->toString())
            ->filter()
            ->values();

        if ($normalized->isEmpty()) {
            return 'guru';
        }

        $first = (string) $normalized->first();
        $last = (string) $normalized->last();

        if ($normalized->count() === 1 || $first === $last) {
            return $first;
        }

        return $first.'.'.$last;
    }

    public function generateDefaultPassword(string $name, int|string|null $seed = null): string
    {
        $base = Str::upper(Str::substr($this->usernameBaseFromName($name), 0, 3));
        $base = str_pad($base, 3, 'G');
        $seedNumber = abs((int) $seed);
        $timestamp = now()->format('His');
        $random = Str::upper(Str::random(3));

        return $base.$seedNumber.'!'.$timestamp.$random;
    }

    protected function usernameExists(string $username, ?int $ignoreUserId = null): bool
    {
        return User::query()
            ->where('username', $username)
            ->when($ignoreUserId, fn ($query) => $query->whereKeyNot($ignoreUserId))
            ->exists();
    }

    public static function credentialsAsSafeHtml(array $credentials): string
    {
        $rows = collect($credentials)
            ->map(function (array $item): string {
                $name = e((string) Arr::get($item, 'guru_tendik', '-'));
                $username = e((string) Arr::get($item, 'username', '-'));
                $password = e((string) Arr::get($item, 'password', '-'));
                $status = Arr::get($item, 'created') ? 'Akun baru' : 'Reset default';

                return "<li><strong>{$name}</strong> ({$status})<br>Username: <code>{$username}</code><br>Password default: <code>{$password}</code></li>";
            })
            ->implode('');

        return '<ul class="list-disc space-y-2 pl-4 text-xs">'.$rows.'</ul>';
    }

    public static function credentialsAsShareText(array $credentials): string
    {
        return self::credentialsAsFormalWhatsappText($credentials);
    }

    public static function credentialsAsFormalWhatsappText(array $credentials): string
    {
        if (blank($credentials)) {
            return 'Tidak ada kredensial akun yang diproses.';
        }

        $lines = [
            'Assalamu\'alaikum Bapak/Ibu Guru/Tendik,',
            '',
            'Berikut kami sampaikan kredensial login akun sekolah:',
            '',
            ...self::credentialRowsAsLines($credentials, true),
            'Mohon segera login dan ganti password default demi keamanan akun.',
            'Terima kasih.',
        ];

        return trim(implode(PHP_EOL, $lines));
    }

    public static function credentialsAsQuickWhatsappText(array $credentials): string
    {
        if (blank($credentials)) {
            return 'Tidak ada kredensial akun yang diproses.';
        }

        $lines = [
            'Kredensial login guru/tendik:',
            '',
            ...self::credentialRowsAsLines($credentials, false),
            'Login lalu ganti password default.',
        ];

        return trim(implode(PHP_EOL, $lines));
    }

    public static function credentialsAsFriendlyWhatsappText(array $credentials): string
    {
        if (blank($credentials)) {
            return 'Tidak ada kredensial akun yang diproses.';
        }

        $lines = [
            'Assalamu\'alaikum Bapak/Ibu, izin share akun login sekolah:',
            '',
            ...self::credentialRowsAsLines($credentials, true),
            'Silakan digunakan untuk login, lalu mohon ganti password default saat sudah masuk.',
            'Jika ada kendala, balas chat ini ya.',
        ];

        return trim(implode(PHP_EOL, $lines));
    }

    public static function credentialsAsCopyableHtml(array $credentials): string
    {
        $formalText = e(self::credentialsAsFormalWhatsappText($credentials));
        $friendlyText = e(self::credentialsAsFriendlyWhatsappText($credentials));
        $quickText = e(self::credentialsAsQuickWhatsappText($credentials));

        return <<<HTML
            <div class="space-y-3 text-xs">
                <p class="font-medium">Pilih salah satu template WhatsApp lalu salin dengan sekali klik:</p>

                <section class="js-copy-credentials-template space-y-2 rounded-lg border border-amber-200 bg-amber-50/40 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-semibold text-amber-900">Template Formal (Komunikasi Sekolah)</p>
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
                        <p class="font-semibold text-sky-900">Template Ramah (Koordinasi Harian)</p>
                        <button
                            type="button"
                            class="js-copy-credentials-btn inline-flex min-h-10 items-center justify-center rounded-lg border border-sky-300 bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-800 transition hover:bg-sky-100"
                        >
                            Salin Template Ramah
                        </button>
                    </div>
                    <pre class="js-copy-credentials-text max-h-52 overflow-auto whitespace-pre-wrap rounded-lg border border-sky-200 bg-white p-3 font-mono text-xs leading-5 text-gray-900">{$friendlyText}</pre>
                </section>

                <section class="js-copy-credentials-template space-y-2 rounded-lg border border-gray-200 bg-gray-50/60 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-semibold text-gray-900">Template Singkat (Quick Share)</p>
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

    /**
     * @param  array<int, array<string, mixed>>  $credentials
     * @return array<int, string>
     */
    protected static function credentialRowsAsLines(array $credentials, bool $withStatus): array
    {
        $lines = [];

        foreach (array_values($credentials) as $index => $item) {
            $status = Arr::get($item, 'created') ? 'Akun baru' : 'Reset default';
            $lines[] = ($index + 1).'. '.(string) Arr::get($item, 'guru_tendik', '-');

            if ($withStatus) {
                $lines[] = '   Status: '.$status;
            }

            $lines[] = '   Username: '.(string) Arr::get($item, 'username', '-');
            $lines[] = '   Password: '.(string) Arr::get($item, 'password', '-');
            $lines[] = '';
        }

        return $lines;
    }
}
