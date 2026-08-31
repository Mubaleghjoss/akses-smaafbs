<?php

namespace App\Support\Bk;

use App\Models\BkKasus;
use App\Models\DataSiswa;
use App\Models\Rombel;
use Illuminate\Validation\ValidationException;

class BkKasusSiswaSync
{
    /**
     * Sinkronkan daftar siswa pada satu kasus sekaligus menyimpan snapshot
     * rombel siswa saat kasus dicatat. Snapshot yang sudah ada dipertahankan
     * agar rekap historis tidak berubah ketika siswa naik kelas atau mutasi.
     *
     * @param  array<int, int|string>  $siswaIds
     */
    public static function sync(BkKasus $kasus, array $siswaIds): void
    {
        $ids = collect(self::validateIds($siswaIds, $kasus));

        if ($ids->isEmpty()) {
            $kasus->siswa()->detach();

            return;
        }

        $existing = $kasus->siswa()
            ->pluck('bk_kasus_siswa.rombel_snapshot', 'data_siswa.id');

        $rombelSekarang = DataSiswa::query()
            ->whereIn('id', $ids->all())
            ->pluck('rombel_saat_ini', 'id');

        $payload = $ids
            ->mapWithKeys(function (int $id) use ($existing, $rombelSekarang): array {
                $snapshot = trim((string) ($existing[$id] ?? ''));

                if ($snapshot === '') {
                    $snapshot = Rombel::normalizeName($rombelSekarang[$id] ?? null);
                }

                return [$id => ['rombel_snapshot' => $snapshot !== '' ? $snapshot : null]];
            })
            ->all();

        $kasus->siswa()->sync($payload);
    }

    /**
     * Tambahkan satu siswa ke kasus tanpa mengubah peserta lain.
     */
    public static function attach(BkKasus $kasus, int $siswaId): void
    {
        if ($kasus->siswa()->whereKey($siswaId)->exists()) {
            return;
        }

        self::validateIds([$siswaId], $kasus);

        $snapshot = Rombel::normalizeName(
            DataSiswa::query()->whereKey($siswaId)->value('rombel_saat_ini')
        );

        $kasus->siswa()->attach($siswaId, [
            'rombel_snapshot' => $snapshot !== '' ? $snapshot : null,
        ]);
    }

    /**
     * ID baru wajib siswa aktif dan berada dalam scope akun. Peserta yang
     * sudah tersimpan tetap boleh dipertahankan untuk menjaga data historis.
     *
     * @param  array<int, int|string>  $siswaIds
     * @return array<int, int>
     */
    public static function validateIds(array $siswaIds, ?BkKasus $kasus = null): array
    {
        $ids = collect($siswaIds)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $existingIds = $kasus?->exists
            ? $kasus->siswa()->whereIn('data_siswa.id', $ids->all())->pluck('data_siswa.id')
            : collect();
        $newIds = $ids->diff($existingIds)->values();

        $allowedNewIds = DataSiswa::query()
            ->visibleToUser(auth()->user())
            ->where('status', 'aktif')
            ->whereIn('id', $newIds->all())
            ->pluck('id');

        if ($allowedNewIds->count() !== $newIds->count()) {
            throw ValidationException::withMessages([
                'siswa_ids' => 'Pilihan siswa tidak valid, tidak aktif, atau di luar akses akun Anda.',
            ]);
        }

        return $ids->all();
    }
}
