<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Contracts\SiteSettingsAccessor;
use App\Models\Pengaturan;
use App\Models\ProfilSekolah;
use App\Models\User;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait AuthorizesSarprasDocuments
{
    protected function authorizeSarprasModule(string $prefix): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, Response::HTTP_FORBIDDEN);
        abort_unless(
            $user->hasFullAdminAccess() || $user->canViewModule($prefix),
            Response::HTTP_FORBIDDEN,
        );

        return $user;
    }

    protected function sarprasSchoolName(): string
    {
        return app(SiteSettingsAccessor::class)->siteName();
    }

    /**
     * @return array{site_name:string,address:?string,phone:?string,email:?string,logo_src:?string}
     */
    protected function sarprasLetterhead(): array
    {
        $settings = app(SiteSettingsAccessor::class);
        $profil = SchemaFacade::hasTable('profil_sekolahs')
            ? ProfilSekolah::query()->first()
            : null;

        $address = $profil?->alamat;

        if (! filled($address)) {
            $address = Pengaturan::value('alamat_sekolah', null);
        }

        return [
            'site_name' => (string) ($profil?->nama_sekolah ?: $settings->siteName()),
            'address' => filled($address) ? trim((string) $address) : null,
            'phone' => filled($profil?->kontak_telepon) ? trim((string) $profil->kontak_telepon) : null,
            'email' => filled($profil?->kontak_email) ? trim((string) $profil->kontak_email) : null,
            'logo_src' => $this->printableAssetSource($settings->logoPath() ?: $settings->faviconPath()),
        ];
    }

    protected function printableAssetSource(?string $value): ?string
    {
        $asset = trim((string) $value);

        if ($asset === '') {
            return null;
        }

        if (Str::startsWith($asset, 'data:')) {
            return $asset;
        }

        if (Str::startsWith($asset, ['/storage/', 'storage/'])) {
            $relative = Str::startsWith($asset, '/storage/')
                ? Str::after($asset, '/storage/')
                : Str::after($asset, 'storage/');

            $storagePath = Storage::disk('public')->path($relative);

            return is_file($storagePath) ? $storagePath : null;
        }

        if (preg_match('#^https?://#i', $asset) === 1) {
            $parsed = parse_url($asset, PHP_URL_PATH);

            if (is_string($parsed) && Str::startsWith($parsed, '/storage/')) {
                $storagePath = Storage::disk('public')->path(Str::after($parsed, '/storage/'));

                return is_file($storagePath) ? $storagePath : $asset;
            }

            return $asset;
        }

        return is_file($asset) ? $asset : null;
    }

    protected function renderSarprasPdf(
        string $view,
        array $data,
        string $filename,
        string $orientation = 'landscape',
    ): HttpResponse {
        $pdf = app('dompdf.wrapper');
        $pdf->setOptions([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
        ]);

        return $pdf
            ->loadView($view, array_merge($data, [
                'pdfMode' => true,
                'printMode' => false,
            ]))
            ->setPaper('a4', $orientation)
            ->download($filename);
    }

    protected function rupiah(float|int|string|null $value): string
    {
        return 'Rp '.number_format((float) ($value ?? 0), 0, ',', '.');
    }
}
