<?php

namespace App\Models;

use App\Models\Concerns\HasGoogleDriveSyncState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfilSekolah extends Model
{
    use HasGoogleDriveSyncState;

    protected $table = 'profil_sekolahs';

    protected $guarded = [];

    protected $casts = [
        'singleton_key' => 'integer',
        'tanggal_identitas' => 'date',
        'tanggal_berdiri' => 'date',
        'fasilitas' => 'array',
        'jadwal_kbm' => 'array',
        'menu_makan' => 'array',
        'identitas_tambahan' => 'array',
        'gdrive_upload_progress' => 'integer',
        'gdrive_uploaded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            $record->singleton_key = 1;
        });

        static::saving(function (self $record): void {
            $record->title = $record->normalizeText($record->title) ?? 'Identitas Sekolah';
            $record->nama_sekolah = $record->normalizeText($record->nama_sekolah);
            $record->provinsi = $record->normalizeText($record->provinsi);
            $record->desa_kelurahan = $record->normalizeText($record->desa_kelurahan);
            $record->kecamatan = $record->normalizeText($record->kecamatan);
            $record->alamat = $record->normalizeTextarea($record->alamat);
            $record->kode_pos = $record->normalizeText($record->kode_pos);
            $record->kontak_telepon = $record->normalizeText($record->kontak_telepon);
            $record->kontak_email = $record->normalizeText($record->kontak_email);
            $record->website_url = $record->normalizeUrl($record->website_url);
            $record->status_sekolah = $record->normalizeText($record->status_sekolah);
            $record->kelompok_sekolah = $record->normalizeText($record->kelompok_sekolah);
            $record->terakreditasi = $record->normalizeText($record->terakreditasi);
            $record->tahun_berdiri = $record->normalizeText($record->tahun_berdiri);
            $record->kbm = $record->normalizeText($record->kbm);
            $record->bangunan_sekolah = $record->normalizeText($record->bangunan_sekolah);
            $record->luas_bangunan = $record->normalizeText($record->luas_bangunan);
            $record->organisasi_penyelenggara = $record->normalizeText($record->organisasi_penyelenggara);
            $record->file_akreditasi_path = $record->normalizeText($record->file_akreditasi_path);
            $record->maps_url = $record->normalizeUrl($record->maps_url);
            $record->youtube_url = $record->normalizeUrl($record->youtube_url);
            $record->instagram_url = $record->normalizeUrl($record->instagram_url);
            $record->facebook_url = $record->normalizeUrl($record->facebook_url);
            $record->tiktok_url = $record->normalizeUrl($record->tiktok_url);
            $record->identitas_tambahan = $record->normalizeAdditionalIdentityItems($record->identitas_tambahan);
            $record->fasilitas = $record->normalizeFacilities($record->fasilitas);
            $record->jadwal_kbm = $record->normalizeScheduleItems($record->jadwal_kbm);
            $record->menu_makan = $record->normalizeMealItems($record->menu_makan);
        });
    }

    /**
     * @return array<int, array{label:string, value:string, url:?string}>
     */
    public function identityRows(): array
    {
        $rows = [
            ['label' => 'Nama Sekolah', 'value' => (string) ($this->nama_sekolah ?: $this->title), 'url' => null],
            ['label' => 'Provinsi', 'value' => (string) ($this->provinsi ?: ''), 'url' => null],
            ['label' => 'Desa / Kelurahan', 'value' => (string) ($this->desa_kelurahan ?: ''), 'url' => null],
            ['label' => 'Kecamatan', 'value' => (string) ($this->kecamatan ?: ''), 'url' => null],
            ['label' => 'Alamat', 'value' => (string) ($this->alamat ?: ''), 'url' => null],
            ['label' => 'Kode Pos', 'value' => (string) ($this->kode_pos ?: ''), 'url' => null],
            ['label' => 'Telepon', 'value' => (string) ($this->kontak_telepon ?: ''), 'url' => null],
            ['label' => 'Email', 'value' => (string) ($this->kontak_email ?: ''), 'url' => filled($this->kontak_email) ? 'mailto:'.$this->kontak_email : null],
            ['label' => 'Website', 'value' => (string) ($this->website_url ?: ''), 'url' => $this->website_url],
            ['label' => 'Status Sekolah', 'value' => (string) ($this->status_sekolah ?: ''), 'url' => null],
            ['label' => 'Kelompok Sekolah', 'value' => (string) ($this->kelompok_sekolah ?: ''), 'url' => null],
            ['label' => 'Terakreditasi', 'value' => (string) ($this->terakreditasi ?: ''), 'url' => null],
            ['label' => 'Tanggal Akreditasi Turun', 'value' => $this->tanggal_identitas?->format('d/m/Y') ?: '', 'url' => null],
            ['label' => 'Tanggal Berdiri Sekolah', 'value' => $this->tanggal_berdiri?->format('d/m/Y') ?: (string) ($this->tahun_berdiri ?: ''), 'url' => null],
            ['label' => 'KBM', 'value' => (string) ($this->kbm ?: ''), 'url' => null],
            ['label' => 'Bangunan Sekolah', 'value' => (string) ($this->bangunan_sekolah ?: ''), 'url' => null],
            ['label' => 'Luas Bangunan', 'value' => (string) ($this->luas_bangunan ?: ''), 'url' => null],
            ['label' => 'Organisasi Penyelenggara', 'value' => (string) ($this->organisasi_penyelenggara ?: ''), 'url' => null],
        ];

        $additional = collect($this->additionalIdentityItems())
            ->map(fn (array $item): array => [
                'label' => $item['label'],
                'value' => $item['value'],
                'url' => filled($item['url'] ?? null) ? (string) $item['url'] : null,
            ]);

        return collect($rows)
            ->concat($additional)
            ->filter(fn (array $row): bool => trim((string) ($row['value'] ?? '')) !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label:string, value:string, url:?string}>
     */
    public function additionalIdentityItems(): array
    {
        return $this->normalizeAdditionalIdentityItems($this->identitas_tambahan);
    }

    /**
     * @return array<int, array{nama:string, foto:?string, keterangan:?string}>
     */
    public function facilities(): array
    {
        return $this->normalizeFacilities($this->fasilitas);
    }

    /**
     * @return array<int, array{waktu:string, kegiatan:string}>
     */
    public function scheduleItems(): array
    {
        return $this->normalizeScheduleItems($this->jadwal_kbm);
    }

    /**
     * @return array<int, array{hari:string, menu:string}>
     */
    public function mealMenuItems(): array
    {
        return $this->normalizeMealItems($this->menu_makan);
    }

    /**
     * @return array<int, array{label:string, url:string}>
     */
    public function socialLinks(): array
    {
        return collect([
            ['label' => 'YouTube', 'url' => $this->youtube_url],
            ['label' => 'Instagram', 'url' => $this->instagram_url],
            ['label' => 'Facebook', 'url' => $this->facebook_url],
            ['label' => 'TikTok', 'url' => $this->tiktok_url],
        ])
            ->filter(fn (array $item): bool => filled($item['url']))
            ->values()
            ->all();
    }

    public function resolvedAccreditationFileUrl(): ?string
    {
        $path = trim((string) $this->file_akreditasi_path);

        return $path !== '' ? Storage::disk('public')->url($path) : null;
    }

    public function hasUploadableFiles(): bool
    {
        return filled($this->file_akreditasi_path);
    }

    /**
     * @return array{label: string, name: string, absolute_path: string, mime_type: string}|null
     */
    public function googleDriveUploadFile(): ?array
    {
        $path = trim((string) $this->file_akreditasi_path);

        if ($path === '') {
            return null;
        }

        return [
            'label' => 'file akreditasi',
            'name' => $this->buildGoogleDriveFileName($path),
            'absolute_path' => Storage::disk('public')->path($path),
            'mime_type' => Storage::disk('public')->mimeType($path) ?: 'application/octet-stream',
        ];
    }

    protected function normalizeText(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected function normalizeTextarea(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected function normalizeUrl(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected function buildGoogleDriveFileName(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $base = Str::slug($this->nama_sekolah ?: $this->title ?: 'dokumen-akreditasi');
        $base = $base !== '' ? 'dokumen-akreditasi-'.$base : 'dokumen-akreditasi';

        return $extension !== ''
            ? $base.'.'.Str::lower($extension)
            : $base;
    }

    /**
     * @param  mixed  $items
     * @return array<int, array{label:string, value:string, url:?string}>
     */
    protected function normalizeAdditionalIdentityItems(mixed $items): array
    {
        return collect(Arr::wrap($items))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'label' => trim((string) ($item['label'] ?? '')),
                'value' => trim((string) ($item['value'] ?? '')),
                'url' => $this->normalizeUrl($item['url'] ?? null),
            ])
            ->filter(fn (array $item): bool => $item['label'] !== '' && $item['value'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $items
     * @return array<int, array{nama:string, foto:?string, keterangan:?string}>
     */
    protected function normalizeFacilities(mixed $items): array
    {
        return collect(Arr::wrap($items))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item): array {
                return [
                    'nama' => trim((string) ($item['nama'] ?? '')),
                    'foto' => $this->normalizeText($item['foto'] ?? null),
                    'keterangan' => $this->normalizeTextarea($item['keterangan'] ?? null),
                ];
            })
            ->filter(fn (array $item): bool => $item['nama'] !== '' || filled($item['foto']) || filled($item['keterangan']))
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $items
     * @return array<int, array{waktu:string, kegiatan:string}>
     */
    protected function normalizeScheduleItems(mixed $items): array
    {
        return collect(Arr::wrap($items))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'waktu' => trim((string) ($item['waktu'] ?? '')),
                'kegiatan' => trim((string) ($item['kegiatan'] ?? '')),
            ])
            ->filter(fn (array $item): bool => $item['waktu'] !== '' || $item['kegiatan'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $items
     * @return array<int, array{hari:string, menu:string}>
     */
    protected function normalizeMealItems(mixed $items): array
    {
        return collect(Arr::wrap($items))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'hari' => trim((string) ($item['hari'] ?? '')),
                'menu' => trim((string) ($item['menu'] ?? '')),
            ])
            ->filter(fn (array $item): bool => $item['hari'] !== '' || $item['menu'] !== '')
            ->values()
            ->all();
    }
}
