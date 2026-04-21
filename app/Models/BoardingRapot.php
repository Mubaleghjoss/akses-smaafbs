<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoardingStudent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardingRapot extends Model
{
    use BelongsToBoardingStudent;

    public const STATUS_OPTIONS = [
        'draft' => 'Draft',
        'review' => 'Review',
        'siap_export' => 'Siap Export',
    ];

    public const PREDIKAT_OPTIONS = [
        'mumtaz' => 'Mumtaz',
        'jayyid_jiddan' => 'Jayyid Jiddan',
        'jayyid' => 'Jayyid',
        'maqbul' => 'Maqbul',
        'perlu_pembinaan' => 'Perlu Pembinaan',
    ];

    protected static ?string $pamongOwnershipColumn = 'pamong_user_id';

    /**
     * @var array<string, string>|null
     */
    protected static ?array $periodeTahunOptionsCache = null;

    /**
     * @var array<string, ?string>|null
     */
    protected static ?array $documentSettingSnapshotCache = null;

    protected $table = 'boarding_rapots';

    protected $guarded = [];

    protected $casts = [
        'tanggal_rapot' => 'date',
        'generated_at' => 'datetime',
        'rekap_payload' => 'array',
        'pamong_user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if (blank($record->pamong_user_id) && auth()->user()?->isBoardingPamong()) {
                $record->pamong_user_id = auth()->id();
            }

            if ($record->pamong_user_id && blank($record->wali_pamong_nama)) {
                $record->wali_pamong_nama = User::query()->whereKey($record->pamong_user_id)->value('name');
            }
        });
    }

    public static function statusOptions(): array
    {
        return self::STATUS_OPTIONS;
    }

    public static function predikatOptions(): array
    {
        return self::PREDIKAT_OPTIONS;
    }

    public static function periodeTahunOptions(): array
    {
        return static::$periodeTahunOptionsCache ??= static::query()
            ->whereNotNull('periode_tahun')
            ->select('periode_tahun')
            ->distinct()
            ->orderByDesc('periode_tahun')
            ->pluck('periode_tahun', 'periode_tahun')
            ->toArray();
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(DataSiswa::class, 'siswa_id');
    }

    public function pamongUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pamong_user_id');
    }

    public function scopeForDocument(Builder $query, mixed $user): Builder
    {
        return $query
            ->with([
                'siswa.boardingPencapaian.details',
                'siswa.boardingPencapaian.updates',
                'siswa.boardingKonselingMts' => fn ($konselingQuery) => $konselingQuery
                    ->latest('tanggal_konseling')
                    ->latest('id'),
                'siswa.boardingKeuanganSiswa.transaksis',
                'pamongUser',
            ])
            ->visibleToUser($user);
    }

    public function buildRekapPayload(): array
    {
        $this->loadMissing([
            'siswa.boardingPencapaian.details',
            'siswa.boardingPencapaian.updates',
            'siswa.boardingKonselingMts' => fn ($konselingQuery) => $konselingQuery
                ->latest('tanggal_konseling')
                ->latest('id'),
            'siswa.boardingKeuanganSiswa.transaksis',
            'pamongUser',
        ]);

        $settings = static::documentSettingSnapshot();

        $siswa = $this->siswa;
        $pencapaian = $this->resolveOwnedPencapaian($siswa?->boardingPencapaian);
        /** @var Collection<int, BoardingKonselingMt> $konselings */
        $konselings = $this->resolveOwnedKonselings($siswa?->boardingKonselingMts ?? new Collection);
        $keuangan = $this->resolveOwnedKeuangan($siswa?->boardingKeuanganSiswa);
        $signatureProfile = $this->resolveSignatureProfile();

        return [
            'school' => [
                'nama' => $settings['nama_sekolah'] ?? 'SMA AFBS',
                'boarding_label' => $settings['boarding_label'] ?? 'Boarding School',
                'alamat' => $settings['alamat_sekolah'],
                'kota' => $this->tempat_cetak ?: ($settings['kota_rapot_boarding'] ?? 'Bogor'),
            ],
            'siswa' => [
                'nama' => $siswa?->nama,
                'rombel' => $siswa?->rombel_saat_ini,
                'jk' => $siswa?->jk,
                'status' => DataSiswa::statusLabel($siswa?->status),
            ],
            'rapot' => [
                'periode_tahun' => $this->periode_tahun,
                'semester' => ucfirst((string) $this->semester),
                'tanggal_rapot' => optional($this->tanggal_rapot)->format('d M Y'),
                'status_rapot' => self::STATUS_OPTIONS[$this->status_rapot] ?? $this->status_rapot,
                'nomor_dokumen' => $this->nomor_dokumen,
                'predikat_boarding' => self::PREDIKAT_OPTIONS[$this->predikat_boarding] ?? $this->predikat_boarding,
            ],
            'pencapaian' => [
                'status' => $pencapaian ? BoardingPencapaian::statusOptions()[$pencapaian->status_pencapaian] ?? $pencapaian->status_pencapaian : '-',
                'target' => [
                    'surat' => (int) ($pencapaian?->target_jumlah_surat ?? 0),
                    'doa' => (int) ($pencapaian?->target_jumlah_doa ?? 0),
                    'hadits' => (int) ($pencapaian?->target_jumlah_hadits ?? 0),
                ],
                'realisasi' => [
                    'surat' => (int) ($pencapaian?->jumlah_surat_dihafal ?? 0),
                    'doa' => (int) ($pencapaian?->jumlah_doa_dihafal ?? 0),
                    'hadits' => (int) ($pencapaian?->jumlah_hadits_dihafal ?? 0),
                ],
                'surat_quran_tuntas' => $pencapaian?->surat_quran_tuntas,
                'hadits_tuntas' => $pencapaian?->hadits_tuntas,
                'hafalan_surat' => $pencapaian?->hafalan_surat,
                'hafalan_doa' => $pencapaian?->hafalan_doa,
                'hafalan_lainnya' => $pencapaian?->hafalan_lainnya,
                'target_berikutnya' => $pencapaian?->target_berikutnya,
                'catatan' => $pencapaian?->catatan,
                'detail_kelompok' => $this->buildDetailGroups($pencapaian?->details ?? new Collection),
            ],
            'konseling' => $konselings->take(5)->map(fn (BoardingKonselingMt $konseling): array => [
                'tanggal' => optional($konseling->tanggal_konseling)->format('d M Y'),
                'kategori' => $konseling->kategori,
                'prioritas' => $konseling->prioritas,
                'status_tindak_lanjut' => $konseling->status_tindak_lanjut,
                'ringkasan_masalah' => $konseling->ringkasan_masalah,
                'tindak_lanjut' => $konseling->tindak_lanjut,
                'konselor' => $konseling->konselor,
            ])->values()->all(),
            'keuangan' => [
                'pamong_nama' => $keuangan?->pamong_nama,
                'kategori_asrama' => $keuangan?->kategori_asrama,
                'titipan_masuk' => (int) ($keuangan?->total_titipan ?? 0),
                'total_titipan' => (int) ($keuangan?->total_titipan ?? 0),
                'pemberian_uang_saku' => (int) ($keuangan?->total_pemberian ?? 0),
                'total_pemberian' => (int) ($keuangan?->total_pemberian ?? 0),
                'setoran_kas' => (int) ($keuangan?->total_kas ?? 0),
                'total_kas' => (int) ($keuangan?->total_kas ?? 0),
                'saldo_tersisa' => (int) ($keuangan?->saldo_tersisa ?? 0),
            ],
            'signatures' => $signatureProfile,
        ];
    }

    public function syncFromSources(bool $overwriteNarratives = false): void
    {
        $payload = $this->buildRekapPayload();

        $pencapaian = $payload['pencapaian'];
        $konseling = collect($payload['konseling']);
        $keuangan = $payload['keuangan'];
        $groups = collect($pencapaian['detail_kelompok'] ?? []);

        $ringkasan = array_filter([
            'Status pencapaian: '.($pencapaian['status'] ?: '-'),
            'Realisasi surat/doa/hadits: '
                .$pencapaian['realisasi']['surat'].' / '
                .$pencapaian['realisasi']['doa'].' / '
                .$pencapaian['realisasi']['hadits'],
            $groups->isNotEmpty() ? 'Kategori target aktif: '.$groups->pluck('judul')->implode(', ') : null,
            filled($pencapaian['surat_quran_tuntas']) ? 'Quran tuntas: '.$pencapaian['surat_quran_tuntas'] : null,
            filled($pencapaian['hadits_tuntas']) ? 'Hadits tuntas: '.$pencapaian['hadits_tuntas'] : null,
            filled($pencapaian['hafalan_doa']) ? 'Doa: '.$pencapaian['hafalan_doa'] : null,
            filled($pencapaian['hafalan_lainnya']) ? 'Hafalan lainnya: '.$pencapaian['hafalan_lainnya'] : null,
        ]);

        $catatanPamong = array_filter([
            $konseling->isNotEmpty() ? 'Konseling terakhir: '.($konseling->first()['ringkasan_masalah'] ?: '-') : null,
            filled($pencapaian['catatan']) ? 'Catatan pembinaan: '.$pencapaian['catatan'] : null,
            'Sisa titipan di pamong: '.BoardingKeuanganSiswa::formatRupiah((int) ($keuangan['saldo_tersisa'] ?? 0)),
        ]);

        $rekomendasi = array_filter([
            filled($pencapaian['target_berikutnya']) ? 'Target berikutnya: '.$pencapaian['target_berikutnya'] : null,
            $konseling->isNotEmpty() ? 'Tindak lanjut konseling: '.($konseling->first()['tindak_lanjut'] ?: '-') : null,
        ]);

        $signatureProfile = $payload['signatures'];

        $this->forceFill([
            'generated_at' => now(),
            'rekap_payload' => $payload,
            'nomor_dokumen' => $this->nomor_dokumen ?: $this->generateDefaultNomorDokumen(),
            'ringkasan_pencapaian' => $overwriteNarratives || blank($this->ringkasan_pencapaian)
                ? implode(PHP_EOL, $ringkasan)
                : $this->ringkasan_pencapaian,
            'catatan_pamong' => $overwriteNarratives || blank($this->catatan_pamong)
                ? implode(PHP_EOL, $catatanPamong)
                : $this->catatan_pamong,
            'rekomendasi_tindak_lanjut' => $overwriteNarratives || blank($this->rekomendasi_tindak_lanjut)
                ? implode(PHP_EOL, $rekomendasi)
                : $this->rekomendasi_tindak_lanjut,
            'wali_pamong_nama' => $this->wali_pamong_nama ?: $signatureProfile['wali_pamong_nama'],
            'kepala_boarding_nama' => $this->kepala_boarding_nama ?: $signatureProfile['kepala_boarding_nama'],
            'mudir_asrama_nama' => $this->mudir_asrama_nama ?: $signatureProfile['mudir_asrama_nama'],
            'tempat_cetak' => $this->tempat_cetak ?: ($payload['school']['kota'] ?? null),
        ])->saveQuietly();
    }

    protected function buildDetailGroups(Collection $details): array
    {
        return collect(BoardingPencapaian::detailCategoryOptions())
            ->map(function (string $label, string $category) use ($details): ?array {
                $rows = $details
                    ->where('kategori_detail', $category)
                    ->values()
                    ->map(fn (BoardingPencapaianDetail $detail): array => [
                        'nama_target' => $detail->nama_target,
                        'target_nilai' => (int) $detail->target_nilai,
                        'capaian_nilai' => (int) $detail->capaian_nilai,
                        'satuan' => $detail->satuan,
                        'status_detail' => BoardingPencapaian::detailStatusOptions()[$detail->status_detail] ?? $detail->status_detail,
                        'tuntas_at' => optional($detail->tuntas_at)->format('d M Y'),
                        'detail' => $detail->detail,
                    ])
                    ->all();

                if ($rows === []) {
                    return null;
                }

                return [
                    'kategori' => $category,
                    'judul' => $label,
                    'rows' => $rows,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function resolveSignatureProfile(): array
    {
        $settings = static::documentSettingSnapshot();

        return [
            'wali_pamong_nama' => $this->wali_pamong_nama ?: ($this->pamongUser?->name ?: ($settings['wali_pamong_boarding_nama'] ?? '-')),
            'kepala_boarding_nama' => $this->kepala_boarding_nama ?: ($settings['kepala_boarding_nama'] ?? '-'),
            'mudir_asrama_nama' => $this->mudir_asrama_nama ?: ($settings['mudir_asrama_nama'] ?? '-'),
        ];
    }

    /**
     * @return array<string, ?string>
     */
    protected static function documentSettingSnapshot(): array
    {
        return static::$documentSettingSnapshotCache ??= Pengaturan::values(
            [
                'nama_sekolah',
                'boarding_label',
                'alamat_sekolah',
                'kota_rapot_boarding',
                'wali_pamong_boarding_nama',
                'kepala_boarding_nama',
                'mudir_asrama_nama',
            ],
            [
                'nama_sekolah' => 'SMA AFBS',
                'boarding_label' => 'Boarding School',
                'kota_rapot_boarding' => 'Bogor',
                'wali_pamong_boarding_nama' => '-',
                'kepala_boarding_nama' => '-',
                'mudir_asrama_nama' => '-',
            ],
        );
    }

    protected function resolveOwnedPencapaian(?BoardingPencapaian $pencapaian): ?BoardingPencapaian
    {
        if (! $pencapaian || blank($this->pamong_user_id)) {
            return $pencapaian;
        }

        return (int) $pencapaian->pamong_user_id === (int) $this->pamong_user_id
            ? $pencapaian
            : null;
    }

    /**
     * @param  Collection<int, BoardingKonselingMt>  $konselings
     * @return Collection<int, BoardingKonselingMt>
     */
    protected function resolveOwnedKonselings(Collection $konselings): Collection
    {
        if (blank($this->pamong_user_id)) {
            return $konselings;
        }

        return $konselings
            ->where('pamong_user_id', (int) $this->pamong_user_id)
            ->values();
    }

    protected function resolveOwnedKeuangan(?BoardingKeuanganSiswa $keuangan): ?BoardingKeuanganSiswa
    {
        if (! $keuangan || blank($this->pamong_user_id)) {
            return $keuangan;
        }

        return (int) $keuangan->pamong_user_id === (int) $this->pamong_user_id
            ? $keuangan
            : null;
    }

    protected function generateDefaultNomorDokumen(): string
    {
        $periode = str_replace(['/', ' '], '-', (string) $this->periode_tahun);
        $semester = strtoupper((string) $this->semester);
        $id = str_pad((string) ($this->getKey() ?: 0), 3, '0', STR_PAD_LEFT);

        return "RB/{$periode}/{$semester}/{$id}";
    }
}
