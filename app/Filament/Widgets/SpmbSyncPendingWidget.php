<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DataSiswaResource;
use App\Support\SpmbSync\SpmbApiClient;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Pemberitahuan "ada siswa lulus SPMB yang siap disinkron".
 *
 * Muncul di dashboard app. Hasil pemeriksaan DI-CACHE 15 menit supaya membuka
 * dashboard tidak memanggil SPMB berulang kali — pemeriksaan hanya menyentuh
 * jaringan sekali per periode cache, bukan setiap kali halaman dibuka.
 *
 * Hanya ditampilkan kepada pengguna yang boleh menambah data siswa (admin),
 * karena hanya mereka yang dapat menindaklanjuti dengan menyinkron.
 */
class SpmbSyncPendingWidget extends Widget
{
    protected string $view = 'filament.widgets.spmb-sync-pending';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -3;

    private const CACHE_KEY = 'spmb_sync:ringkasan_perubahan';

    private const CACHE_TTL_MENIT = 15;

    public static function canView(): bool
    {
        if (! Auth::check() || ! DataSiswaResource::canCreate()) {
            return false;
        }

        $ringkasan = static::ringkasan();

        return ($ringkasan['total'] ?? 0) > 0;
    }

    public function getRingkasan(): array
    {
        return static::ringkasan();
    }

    public function getSyncUrl(): string
    {
        // Nama halaman pada DataSiswaResource::getPages() adalah 'spmb-sync'
        // (route-nya '/sync-spmb') — mudah tertukar.
        return DataSiswaResource::getUrl('spmb-sync');
    }

    /**
     * Perbarui paksa (dipakai tombol "Periksa lagi").
     */
    public function periksaUlang(): void
    {
        Cache::forget(self::CACHE_KEY);
        static::ringkasan();
    }

    /**
     * @return array{total:int, baru:int, berubah:int, siswa_baru:int, pindahan:int, daftar:array<int, array<string, mixed>>, error:string|null}
     */
    protected static function ringkasan(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(self::CACHE_TTL_MENIT),
            function (): array {
                $kosong = [
                    'total' => 0, 'baru' => 0, 'berubah' => 0,
                    'siswa_baru' => 0, 'pindahan' => 0, 'daftar' => [], 'error' => null,
                ];

                // Token belum dikonfigurasi -> jangan ganggu dashboard.
                if (blank(config('services.spmb_sync.token'))) {
                    return $kosong;
                }

                try {
                    return [...app(SpmbApiClient::class)->ringkasanPerubahan(), 'error' => null];
                } catch (Throwable $exception) {
                    report($exception);

                    // Gagal menghubungi SPMB tidak boleh membuat dashboard error.
                    return [...$kosong, 'error' => str($exception->getMessage())->limit(160)->toString()];
                }
            },
        );
    }
}
