<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoardingStudent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

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

    public const SETTING_LOGO_PATH = 'boarding_rapot_logo_path';

    public const SETTING_KOP_SITE_NAME = 'boarding_rapot_kop_site_name';

    public const SETTING_KOP_SUBTITLE = 'boarding_rapot_kop_subtitle';

    public const SETTING_KOP_ADDRESS = 'boarding_rapot_kop_address';

    public const SETTING_KOP_CONTACT = 'boarding_rapot_kop_contact';

    public const SETTING_PROLOG = 'boarding_rapot_prolog';

    public const SETTING_KOTA = 'kota_rapot_boarding';

    public const SETTING_WALI_LABEL = 'boarding_rapot_wali_label';

    public const SETTING_KEPALA_LABEL = 'boarding_rapot_kepala_label';

    public const SETTING_MUDIR_LABEL = 'boarding_rapot_mudir_label';

    public const SETTING_WALI_NAME = 'wali_pamong_boarding_nama';

    public const SETTING_KEPALA_NAME = 'kepala_boarding_nama';

    public const SETTING_MUDIR_NAME = 'mudir_asrama_nama';

    public const DEFAULT_PROLOG = "Assalamu'alaikum warahmatullahi wabarakatuh.\n\nBersama ini kami sampaikan kepada Bapak/Ibu Orang Tua/Wali Santri laporan perkembangan hasil pembelajaran santri di Boarding SMA Al Furqon Boarding School. Kami berharap laporan ini dapat membantu Bapak/Ibu mengetahui perkembangan pembinaan santri selama mengikuti kegiatan boarding. Adapun uraian laporan tersebut adalah sebagai berikut:";

    public const BOARDING_CLASS_LABELS = [
        'pegon_bacaan' => 'Kelas Pegon Bacaan',
        'lambatan' => 'Kelas Lambatan',
        'cepatan' => 'Kelas Cepatan',
        'materi_tambahan_hafalan' => 'Kelas Materi Tambahan (Persiapan Saringan)',
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
        'administrasi_rapot_items' => 'array',
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

            if (Schema::hasColumn($record->getTable(), 'administrasi_rapot_items')) {
                $record->administrasi_rapot_items = static::normalizeAdministrasiRapotItems($record->administrasi_rapot_items ?? []);
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

    public static function documentSettings(): array
    {
        return static::documentSettingSnapshot();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function saveDocumentSettings(array $settings): void
    {
        foreach (static::documentEditableSettingKeys() as $key) {
            Pengaturan::query()->updateOrCreate(
                ['nama_pengaturan' => $key],
                ['nilai_pengaturan' => static::normalizeDocumentSettingValue($settings[$key] ?? null)]
            );
        }

        static::flushDocumentSettingSnapshot();
    }

    public static function flushDocumentSettingSnapshot(): void
    {
        static::$documentSettingSnapshotCache = null;
    }

    public static function boardingClassOptions(): array
    {
        return self::BOARDING_CLASS_LABELS;
    }

    public static function normalizeBoardingClassKey(mixed $key): ?string
    {
        $key = trim((string) $key);

        return isset(self::BOARDING_CLASS_LABELS[$key]) ? $key : null;
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

    public static function defaultPeriodeTahun(mixed $date = null): string
    {
        $date = $date ? Carbon::parse($date) : now();
        $startYear = $date->month >= 7 ? $date->year : $date->year - 1;

        return $startYear.'/'.($startYear + 1);
    }

    public static function defaultSemester(mixed $date = null): string
    {
        $date = $date ? Carbon::parse($date) : now();

        return $date->month >= 7 ? 'ganjil' : 'genap';
    }

    /**
     * @return array{created: int, updated: int, total: int}
     */
    public static function syncFromFilledPencapaians(
        mixed $user = null,
        ?string $periodeTahun = null,
        ?string $semester = null,
        mixed $tanggalRapot = null,
        string $statusRapot = 'draft',
        bool $overwriteNarratives = true,
    ): array {
        $periodeTahun ??= static::defaultPeriodeTahun();
        $semester ??= static::defaultSemester();
        $tanggalRapot = $tanggalRapot ? Carbon::parse($tanggalRapot)->toDateString() : now()->toDateString();

        $created = 0;
        $updated = 0;

        BoardingPencapaian::query()
            ->with('siswa:id,nama,rombel_saat_ini')
            ->visibleToUser($user)
            ->whereHas('siswa', fn (Builder $query): Builder => DataSiswa::applyVisibleScope($query, $user))
            ->withFilledTargetData()
            ->orderBy('siswa_id')
            ->get()
            ->each(function (BoardingPencapaian $pencapaian) use ($periodeTahun, $semester, $tanggalRapot, $statusRapot, $overwriteNarratives, &$created, &$updated): void {
                $rapot = static::query()->firstOrNew([
                    'siswa_id' => $pencapaian->siswa_id,
                    'periode_tahun' => $periodeTahun,
                    'semester' => $semester,
                ]);

                $isNew = ! $rapot->exists;

                $rapot->forceFill([
                    'pamong_user_id' => filled($pencapaian->pamong_user_id) ? $pencapaian->pamong_user_id : $rapot->pamong_user_id,
                    'tanggal_rapot' => $rapot->tanggal_rapot ?: $tanggalRapot,
                    'status_rapot' => $rapot->status_rapot ?: $statusRapot,
                ])->save();

                $rapot->syncFromSources(
                    overwriteNarratives: $overwriteNarratives,
                    overwritePencapaianSummary: true,
                );

                if ($isNew) {
                    $created++;
                } else {
                    $updated++;
                }
            });

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => $created + $updated,
        ];
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
                'siswa.boardingPencapaian.hafalanAssessments.point',
                'siswa.boardingPencapaian.hafalanAssessments.reviewerUser',
                'siswa.boardingPencapaian.maknaProgresses.updatedByUser',
                'siswa.boardingPencapaian.mtProgresses.updatedByUser',
                'siswa.boardingPencapaian.materiProgresses.updatedByUser',
                'siswa.boardingPencapaian.bacaanAssessments.reviewerUser',
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
            'siswa.boardingPencapaian.hafalanAssessments.point',
            'siswa.boardingPencapaian.hafalanAssessments.reviewerUser',
            'siswa.boardingPencapaian.maknaProgresses.updatedByUser',
            'siswa.boardingPencapaian.mtProgresses.updatedByUser',
            'siswa.boardingPencapaian.materiProgresses.updatedByUser',
            'siswa.boardingPencapaian.bacaanAssessments.reviewerUser',
            'siswa.boardingKonselingMts' => fn ($konselingQuery) => $konselingQuery
                ->latest('tanggal_konseling')
                ->latest('id'),
            'siswa.boardingKeuanganSiswa.transaksis',
            'pamongUser',
        ]);

        $settings = static::documentSettingSnapshot();

        $siswa = $this->siswa;
        $pencapaian = $this->resolveOwnedPencapaian($siswa?->boardingPencapaian);
        $materiRapotScope = BoardingPencapaian::normalizeMateriRapotScope($pencapaian?->materi_rapot_scope);
        $hafalanAssessments = $pencapaian?->hafalanAssessments ?? new Collection;
        $maknaProgresses = $pencapaian?->maknaProgresses ?? new Collection;
        $mtProgresses = $pencapaian?->mtProgresses ?? new Collection;
        $materiProgresses = $pencapaian?->materiProgresses ?? new Collection;
        $bacaanAssessments = $pencapaian?->bacaanAssessments ?? new Collection;
        /** @var Collection<int, BoardingKonselingMt> $konselings */
        $konselings = $this->resolveOwnedKonselings($siswa?->boardingKonselingMts ?? new Collection);
        $keuangan = $this->resolveOwnedKeuangan($siswa?->boardingKeuanganSiswa);
        $signatureProfile = $this->resolveSignatureProfile();
        $kelasBoardingAutoKey = $this->resolveKelasBoardingKey($hafalanAssessments, $pencapaian);
        $kelasBoardingOverrideKey = static::normalizeBoardingClassKey($this->kelas_boarding_override ?? null);
        $kelasBoardingKey = $kelasBoardingOverrideKey ?? $kelasBoardingAutoKey;

        return [
            'school' => [
                'nama' => $settings[self::SETTING_KOP_SITE_NAME] ?: ($settings['nama_sekolah'] ?? 'SMA AFBS'),
                'boarding_label' => $settings[self::SETTING_KOP_SUBTITLE] ?: ($settings['boarding_label'] ?? 'Boarding School'),
                'alamat' => $settings[self::SETTING_KOP_ADDRESS] ?: ($settings['alamat_sekolah'] ?? null),
                'kota' => static::normalizeSignatureText($settings[self::SETTING_KOTA] ?? null)
                    ?? static::normalizeSignatureText($this->tempat_cetak)
                    ?? 'Bogor',
            ],
            'document' => [
                'prolog' => $settings[self::SETTING_PROLOG] ?: self::DEFAULT_PROLOG,
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
                'kelas_boarding' => self::BOARDING_CLASS_LABELS[$kelasBoardingKey] ?? self::BOARDING_CLASS_LABELS['pegon_bacaan'],
                'kelas_boarding_key' => $kelasBoardingKey,
                'kelas_boarding_auto' => self::BOARDING_CLASS_LABELS[$kelasBoardingAutoKey] ?? self::BOARDING_CLASS_LABELS['pegon_bacaan'],
                'kelas_boarding_auto_key' => $kelasBoardingAutoKey,
                'kelas_boarding_override' => $kelasBoardingOverrideKey ? self::BOARDING_CLASS_LABELS[$kelasBoardingOverrideKey] : null,
                'kelas_boarding_override_key' => $kelasBoardingOverrideKey,
                'administrasi_items' => static::normalizeAdministrasiRapotItems($this->administrasi_rapot_items ?? []),
            ],
            'pencapaian' => [
                'status' => $pencapaian ? BoardingPencapaian::statusOptions()[$pencapaian->status_pencapaian] ?? $pencapaian->status_pencapaian : '-',
                'materi_rapot_scope' => $materiRapotScope,
                'materi_rapot_label' => BoardingPencapaian::materiRapotScopeLabel($materiRapotScope),
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
                'hafalan_detail' => $this->buildHafalanGroups($hafalanAssessments),
                'makna' => $this->buildMaknaPayload($maknaProgresses),
                'materi_boarding' => $materiRapotScope === BoardingPencapaian::MATERI_RAPOT_SCOPE_BOARDING
                    ? $this->buildMateriBoardingPayload($pencapaian, $materiProgresses)
                    : $this->buildInactiveMateriBoardingPayload($materiRapotScope),
                'mt' => $materiRapotScope === BoardingPencapaian::MATERI_RAPOT_SCOPE_MT
                    ? $this->buildMtPayload($mtProgresses)
                    : $this->buildInactiveMtPayload($materiRapotScope),
                'bacaan' => $this->buildBacaanPayload($bacaanAssessments),
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

    public function syncFromSources(bool $overwriteNarratives = false, ?bool $overwritePencapaianSummary = null): void
    {
        $overwritePencapaianSummary ??= $overwriteNarratives;

        $payload = $this->buildRekapPayload();

        $pencapaian = $payload['pencapaian'];
        $konseling = collect($payload['konseling']);
        $keuangan = $payload['keuangan'];
        $groups = collect($pencapaian['detail_kelompok'] ?? []);
        $makna = $pencapaian['makna'] ?? [];
        $materiBoarding = $pencapaian['materi_boarding'] ?? [];
        $mt = $pencapaian['mt'] ?? [];
        $bacaan = $pencapaian['bacaan'] ?? [];
        $materiRapotScope = BoardingPencapaian::normalizeMateriRapotScope($pencapaian['materi_rapot_scope'] ?? null);

        $ringkasan = array_filter([
            'Status pencapaian: '.($pencapaian['status'] ?: '-'),
            'Target materi rapot: '.BoardingPencapaian::materiRapotScopeLabel($materiRapotScope),
            'Realisasi surat/doa/hadits: '
                .$pencapaian['realisasi']['surat'].' / '
                .$pencapaian['realisasi']['doa'].' / '
                .$pencapaian['realisasi']['hadits'],
            $groups->isNotEmpty() ? 'Kategori target aktif: '.$groups->pluck('judul')->implode(', ') : null,
            filled($pencapaian['surat_quran_tuntas']) ? 'Quran tuntas: '.$pencapaian['surat_quran_tuntas'] : null,
            filled($pencapaian['hadits_tuntas']) ? 'Hadits tuntas: '.$pencapaian['hadits_tuntas'] : null,
            filled($pencapaian['hafalan_doa']) ? 'Doa: '.$pencapaian['hafalan_doa'] : null,
            filled($pencapaian['hafalan_lainnya']) ? 'Hafalan lainnya: '.$pencapaian['hafalan_lainnya'] : null,
            $materiRapotScope === BoardingPencapaian::MATERI_RAPOT_SCOPE_BOARDING && ((int) ($makna['filled_count'] ?? 0)) > 0
                ? 'Makna: '.$makna['summary_label']
                : null,
            $materiRapotScope === BoardingPencapaian::MATERI_RAPOT_SCOPE_BOARDING && ((int) ($materiBoarding['filled_manual_count'] ?? 0)) > 0
                ? 'Materi Boarding: '.$materiBoarding['summary_label']
                : null,
            $materiRapotScope === BoardingPencapaian::MATERI_RAPOT_SCOPE_MT && ((int) ($mt['filled_count'] ?? 0)) > 0
                ? 'Materi MT: '.$mt['summary_label']
                : null,
            $materiRapotScope === BoardingPencapaian::MATERI_RAPOT_SCOPE_BOARDING && ((int) ($bacaan['total_sessions'] ?? 0)) > 0
                ? 'Bacaan: '.$bacaan['summary_label']
                : null,
        ]);

        $catatanSaranBoarding = $materiRapotScope === BoardingPencapaian::MATERI_RAPOT_SCOPE_BOARDING
            ? $this->formatCatatanSaranFromGroups($materiBoarding['manual_groups'] ?? [])
            : null;
        $catatanSaranMt = $materiRapotScope === BoardingPencapaian::MATERI_RAPOT_SCOPE_MT
            ? $this->formatCatatanSaranFromGroups($mt['groups'] ?? [])
            : null;

        $catatanPamong = array_filter([
            $konseling->isNotEmpty() ? 'Konseling terakhir: '.($konseling->first()['ringkasan_masalah'] ?: '-') : null,
            filled($pencapaian['catatan']) ? 'Catatan pembinaan: '.$pencapaian['catatan'] : null,
            filled($catatanSaranBoarding) ? 'Catatan dan Saran Boarding: '.$catatanSaranBoarding : null,
            filled($catatanSaranMt) ? 'Catatan dan Saran MT: '.$catatanSaranMt : null,
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
            'ringkasan_pencapaian' => $overwritePencapaianSummary || blank($this->ringkasan_pencapaian)
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

    protected function formatCatatanSaranFromGroups(array $groups): ?string
    {
        $items = collect($groups)
            ->filter(function (mixed $group): bool {
                if (! is_array($group)) {
                    return false;
                }

                $groupKey = (string) ($group['group'] ?? '');
                $groupTitle = strtolower((string) ($group['judul'] ?? ''));

                return $groupKey === 'catatan_saran' || str_contains($groupTitle, 'catatan');
            })
            ->flatMap(fn (array $group): array => $group['rows'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(function (array $row): ?string {
                $targetName = $row['target_name'] ?? null;
                $value = $row['grade'] ?? $row['capaian'] ?? null;
                $notes = trim((string) ($row['notes'] ?? ''));

                if (blank($targetName) || (blank($value) && $notes === '')) {
                    return null;
                }

                $summary = $targetName.': '.(filled($value) ? $value : 'Belum Diisi');

                return $notes !== '' ? "{$summary} - {$notes}" : $summary;
            })
            ->filter()
            ->values();

        return $items->isNotEmpty() ? $items->implode('; ') : null;
    }

    protected function buildHafalanGroups(Collection $assessments): array
    {
        return $assessments
            ->filter(fn (BoardingHafalanAssessment $assessment): bool => $assessment->point !== null)
            ->sortBy(fn (BoardingHafalanAssessment $assessment): string => sprintf(
                '%s|%05d|%05d',
                (string) ($assessment->point?->materi_key ?? ''),
                (int) ($assessment->point?->urutan ?? 0),
                (int) ($assessment->point?->id ?? 0),
            ))
            ->groupBy(fn (BoardingHafalanAssessment $assessment): string => (string) ($assessment->point?->materi_key ?? 'lainnya'))
            ->map(function (Collection $rows, string $materiKey): array {
                return [
                    'materi_key' => $materiKey,
                    'judul' => BoardingHafalanPoint::materiLabel($materiKey),
                    'rows' => $rows
                        ->map(fn (BoardingHafalanAssessment $assessment): array => [
                            'nama_point' => $assessment->point?->nama_point,
                            'jenis' => $assessment->point?->jenis,
                            'score' => (int) $assessment->score,
                            'assessed_at' => optional($assessment->assessed_at)->format('d M Y'),
                            'reviewer' => $assessment->reviewerUser?->name ?: ($assessment->reviewer_name ?: '-'),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    protected function buildMaknaPayload(Collection $progresses): array
    {
        $total = BoardingMaknaProgress::defaultTargetCount();
        $khatam = $progresses->where('status', 'khatam')->count();
        $sebagian = $progresses->where('status', 'sebagian')->count();
        $filled = $khatam + $sebagian;
        $blank = max($total - $filled, 0);
        $filledRows = $progresses
            ->whereIn('status', ['sebagian', 'khatam'])
            ->sortBy(fn (BoardingMaknaProgress $progress): string => sprintf(
                '%s|%05d|%05d',
                (string) $progress->target_group,
                (int) $progress->urutan,
                (int) $progress->id,
            ));

        return [
            'total_targets' => $total,
            'khatam_count' => $khatam,
            'partial_count' => $sebagian,
            'blank_count' => $blank,
            'filled_count' => $filled,
            'summary_label' => "Khatam {$khatam}, sebagian {$sebagian}, belum diisi {$blank} dari {$total} target",
            'groups' => collect(BoardingMaknaProgress::groupOptions())
                ->map(function (string $label, string $group) use ($filledRows): ?array {
                    $rows = $filledRows
                        ->where('target_group', $group)
                        ->map(fn (BoardingMaknaProgress $progress): array => [
                            'target_name' => $progress->target_name,
                            'status' => BoardingMaknaProgress::statusLabel($progress->status),
                            'remaining_pages' => $progress->status === 'sebagian' ? $progress->remaining_pages : null,
                            'total_pages' => $progress->status === 'sebagian' ? $progress->total_pages : null,
                            'updated_at' => optional($progress->updated_at)->format('d M Y'),
                            'updated_by' => $progress->updatedByUser?->name ?: '-',
                        ])
                        ->values()
                        ->all();

                    if ($rows === []) {
                        return null;
                    }

                    return [
                        'group' => $group,
                        'judul' => $label,
                        'rows' => $rows,
                    ];
                })
                ->filter()
            ->values()
            ->all(),
        ];
    }

    protected function buildMateriBoardingPayload(?BoardingPencapaian $pencapaian, Collection $progresses): array
    {
        if (! $pencapaian) {
            return [
                'is_active' => true,
                'summary_label' => 'Belum ada pencapaian boarding',
                'filled_manual_count' => 0,
                'bacaan_quran' => ['summary_label' => 'Belum ada riwayat bacaan'],
                'makna_quran' => ['summary_label' => '-'],
                'makna_hadits' => ['summary_label' => '-'],
                'hafalan' => [],
                'manual_groups' => [],
            ];
        }

        $filledRows = $progresses
            ->filter(fn (BoardingMateriProgress $progress): bool => $progress->isFilled())
            ->sortBy(fn (BoardingMateriProgress $progress): string => sprintf(
                '%s|%05d|%05d',
                (string) $progress->target_group,
                (int) $progress->urutan,
                (int) $progress->id,
            ));
        $filled = $filledRows->count();
        $hafalan = BoardingMateriProgress::hafalanSummaries($pencapaian);
        $completeHafalan = collect($hafalan)
            ->filter(fn (array $row): bool => filled($row['grade'] ?? null))
            ->count();

        return [
            'is_active' => true,
            'summary_label' => "{$filled} pengetesan/catatan terisi, {$completeHafalan} kelas hafalan lengkap",
            'filled_manual_count' => $filled,
            'bacaan_quran' => BoardingMateriProgress::bacaanSummary($pencapaian),
            'makna_quran' => BoardingMateriProgress::maknaGroupSummary($pencapaian, 'quran'),
            'makna_hadits' => BoardingMateriProgress::maknaGroupSummary($pencapaian, 'hadits_materi'),
            'hafalan' => $hafalan,
            'manual_groups' => collect(BoardingMateriProgress::groupOptions())
                ->map(function (string $label, string $group) use ($filledRows): ?array {
                    $rows = $filledRows
                        ->where('target_group', $group)
                        ->map(fn (BoardingMateriProgress $progress): array => [
                            'target_name' => $progress->target_name,
                            'grade' => BoardingMateriProgress::gradeLabel($progress->grade),
                            'notes' => $progress->notes,
                            'updated_at' => optional($progress->updated_at)->format('d M Y'),
                            'updated_by' => $progress->updatedByUser?->name ?: '-',
                        ])
                        ->values()
                        ->all();

                    if ($rows === []) {
                        return null;
                    }

                    return [
                        'group' => $group,
                        'judul' => $label,
                        'rows' => $rows,
                    ];
                })
                ->filter()
                ->values()
                ->all(),
        ];
    }

    protected function buildInactiveMateriBoardingPayload(string $activeScope): array
    {
        return [
            'is_active' => false,
            'summary_label' => 'Tidak ditampilkan karena target rapot aktif: '.BoardingPencapaian::materiRapotScopeLabel($activeScope),
            'filled_manual_count' => 0,
            'bacaan_quran' => ['summary_label' => '-'],
            'makna_quran' => ['summary_label' => '-'],
            'makna_hadits' => ['summary_label' => '-'],
            'hafalan' => [],
            'manual_groups' => [],
        ];
    }

    protected function buildMtPayload(Collection $progresses): array
    {
        $total = BoardingMtProgress::defaultTargetCount();
        $filledRows = $progresses
            ->filter(fn (BoardingMtProgress $progress): bool => $progress->isFilled())
            ->sortBy(fn (BoardingMtProgress $progress): string => sprintf(
                '%s|%05d|%05d',
                (string) $progress->target_group,
                (int) $progress->urutan,
                (int) $progress->id,
            ));
        $filled = $filledRows->count();
        $blank = max($total - $filled, 0);

        return [
            'is_active' => true,
            'total_targets' => $total,
            'filled_count' => $filled,
            'blank_count' => $blank,
            'summary_label' => "{$filled} dari {$total} target MT terisi, {$blank} belum diisi",
            'groups' => collect(BoardingMtProgress::groupOptions())
                ->map(function (string $label, string $group) use ($filledRows): ?array {
                    $rows = $filledRows
                        ->where('target_group', $group)
                        ->map(fn (BoardingMtProgress $progress): array => [
                            'target_name' => $progress->target_name,
                            'capaian' => $progress->progressSummary(),
                            'input_type' => $progress->input_type,
                            'progress_value' => $progress->progress_value,
                            'target_total' => $progress->target_total,
                            'unit_label' => $progress->unit_label,
                            'grade_key' => $progress->grade,
                            'grade' => BoardingMtProgress::gradeLabel($progress->grade),
                            'notes' => $progress->notes,
                            'updated_at' => optional($progress->updated_at)->format('d M Y'),
                            'updated_by' => $progress->updatedByUser?->name ?: '-',
                        ])
                        ->values()
                        ->all();

                    if ($rows === []) {
                        return null;
                    }

                    return [
                        'group' => $group,
                        'judul' => $label,
                        'rows' => $rows,
                    ];
                })
                ->filter()
                ->values()
                ->all(),
        ];
    }

    protected function buildInactiveMtPayload(string $activeScope): array
    {
        return [
            'is_active' => false,
            'total_targets' => BoardingMtProgress::defaultTargetCount(),
            'filled_count' => 0,
            'blank_count' => BoardingMtProgress::defaultTargetCount(),
            'summary_label' => 'Tidak ditampilkan karena target rapot aktif: '.BoardingPencapaian::materiRapotScopeLabel($activeScope),
            'groups' => [],
        ];
    }

    protected function buildBacaanPayload(Collection $assessments): array
    {
        $rows = $assessments
            ->sortByDesc(fn (BoardingBacaanAssessment $assessment): string => sprintf(
                '%s|%05d',
                (string) ($assessment->assessed_at?->toDateString() ?? ''),
                (int) $assessment->id,
            ))
            ->values()
            ->map(fn (BoardingBacaanAssessment $assessment): array => [
                'tanggal' => optional($assessment->assessed_at)->format('d M Y'),
                'nilai' => $this->formatBacaanGrades($assessment),
                'pp' => BoardingBacaanAssessment::gradeLabel($assessment->pp_grade),
                'kl' => BoardingBacaanAssessment::gradeLabel($assessment->kl_grade),
                'tj' => BoardingBacaanAssessment::gradeLabel($assessment->tj_grade),
                'mj' => BoardingBacaanAssessment::gradeLabel($assessment->mj_grade),
                'reviewer' => $assessment->reviewerUser?->name ?: ($assessment->reviewer_name ?: '-'),
                'notes' => $assessment->notes,
            ]);

        $latest = $rows->first();
        $total = $rows->count();

        return [
            'total_sessions' => $total,
            'latest' => $latest,
            'summary_label' => $total > 0
                ? "{$total} simakan, terakhir ".($latest['tanggal'] ?? '-').' oleh '.($latest['reviewer'] ?? '-')
                : 'Belum ada riwayat bacaan',
            'rows' => $rows->take(10)->all(),
        ];
    }

    protected function formatBacaanGrades(BoardingBacaanAssessment $assessment): string
    {
        return implode(' | ', [
            'PP '.$assessment->pp_grade,
            'KL '.$assessment->kl_grade,
            'TJ '.$assessment->tj_grade,
            'MJ '.$assessment->mj_grade,
        ]);
    }

    protected function resolveKelasBoardingKey(Collection $assessments, ?BoardingPencapaian $pencapaian): string
    {
        $classKeys = array_keys(self::BOARDING_CLASS_LABELS);
        $assessedByKey = $assessments
            ->filter(fn (BoardingHafalanAssessment $assessment): bool => $assessment->point !== null
                && in_array((string) $assessment->point->materi_key, $classKeys, true)
                && in_array((string) $assessment->point->jenis, BoardingHafalanPoint::hafalanJenis(), true))
            ->groupBy(fn (BoardingHafalanAssessment $assessment): string => (string) $assessment->point->materi_key)
            ->map(fn (Collection $rows): int => $rows->count());

        foreach (array_reverse($classKeys) as $classKey) {
            if ((int) ($assessedByKey[$classKey] ?? 0) > 0) {
                return $classKey;
            }
        }

        if ($pencapaian) {
            $progressKey = collect(BoardingMateriProgress::hafalanSummaries($pencapaian))
                ->filter(fn (array $row): bool => (int) ($row['assessed'] ?? 0) > 0)
                ->sortByDesc(fn (array $row): int => array_search((string) ($row['materi_key'] ?? ''), $classKeys, true) ?: 0)
                ->pluck('materi_key')
                ->first();

            if (isset(self::BOARDING_CLASS_LABELS[$progressKey])) {
                return $progressKey;
            }
        }

        return 'pegon_bacaan';
    }

    protected function resolveSignatureProfile(): array
    {
        $settings = static::documentSettingSnapshot();
        $slot1Label = static::normalizeSignatureText($settings[self::SETTING_WALI_LABEL] ?? null) ?? 'Kepala Sekolah';
        $slot2Label = static::normalizeSignatureText($settings[self::SETTING_KEPALA_LABEL] ?? null) ?? 'Kepala Boarding';
        $slot3Label = static::normalizeSignatureText($settings[self::SETTING_MUDIR_LABEL] ?? null) ?? 'Pamong';
        $pamongName = static::normalizeSignatureText($this->pamongUser?->name);
        $legacyPamongName = static::normalizeSignatureText($this->wali_pamong_nama);
        $slot1RecordName = static::recordSignatureFallback($this->wali_pamong_nama, $pamongName, $slot1Label);
        $slot2RecordName = static::normalizeSignatureText($this->kepala_boarding_nama);
        $slot3RecordName = static::signatureLabelUsesPamongFallback($slot3Label)
            ? ($pamongName ?? $legacyPamongName ?? static::normalizeSignatureText($this->mudir_asrama_nama))
            : static::normalizeSignatureText($this->mudir_asrama_nama);

        return [
            'wali_pamong_label' => $slot1Label,
            'kepala_boarding_label' => $slot2Label,
            'mudir_asrama_label' => $slot3Label,
            'wali_pamong_nama' => static::normalizeSignatureText($settings[self::SETTING_WALI_NAME] ?? null)
                ?? $slot1RecordName
                ?? '-',
            'kepala_boarding_nama' => static::normalizeSignatureText($settings[self::SETTING_KEPALA_NAME] ?? null)
                ?? $slot2RecordName
                ?? '-',
            'mudir_asrama_nama' => static::normalizeSignatureText($settings[self::SETTING_MUDIR_NAME] ?? null)
                ?? $slot3RecordName
                ?? '-',
        ];
    }

    protected static function normalizeSignatureText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' || in_array($text, ['-', ':'], true) ? null : $text;
    }

    /**
     * @return array<int, array{question: string, answer: string}>
     */
    public static function normalizeAdministrasiRapotItems(mixed $items): array
    {
        return collect(is_array($items) ? $items : [])
            ->map(function (mixed $item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $question = trim((string) ($item['question'] ?? $item['pertanyaan'] ?? ''));
                $answer = trim((string) ($item['answer'] ?? $item['jawaban'] ?? ''));

                if ($question === '' && $answer === '') {
                    return null;
                }

                return [
                    'question' => $question !== '' ? $question : 'Pertanyaan',
                    'answer' => $answer !== '' ? $answer : '-',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected static function recordSignatureFallback(mixed $value, ?string $pamongName, string $label): ?string
    {
        $text = static::normalizeSignatureText($value);

        if ($text === null) {
            return static::signatureLabelUsesPamongFallback($label) ? $pamongName : null;
        }

        if (
            ! static::signatureLabelUsesPamongFallback($label)
            && $pamongName !== null
            && strcasecmp($text, $pamongName) === 0
        ) {
            return null;
        }

        return $text;
    }

    protected static function signatureLabelUsesPamongFallback(string $label): bool
    {
        $label = mb_strtolower($label);

        return str_contains($label, 'pamong')
            || str_contains($label, 'pembimbing')
            || str_contains($label, 'asrama');
    }

    /**
     * @return array<string, ?string>
     */
    protected static function documentSettingSnapshot(): array
    {
        return static::$documentSettingSnapshotCache ??= Pengaturan::values(
            array_keys(static::documentSettingDefaults()),
            static::documentSettingDefaults(),
        );
    }

    /**
     * @return array<string, ?string>
     */
    protected static function documentSettingDefaults(): array
    {
        return [
            'nama_sekolah' => 'SMA AFBS',
            'boarding_label' => 'Boarding School',
            'alamat_sekolah' => null,
            self::SETTING_LOGO_PATH => null,
            self::SETTING_KOP_SITE_NAME => null,
            self::SETTING_KOP_SUBTITLE => null,
            self::SETTING_KOP_ADDRESS => null,
            self::SETTING_KOP_CONTACT => null,
            self::SETTING_PROLOG => self::DEFAULT_PROLOG,
            self::SETTING_KOTA => 'Bogor',
            self::SETTING_WALI_LABEL => 'Kepala Sekolah',
            self::SETTING_KEPALA_LABEL => 'Kepala Boarding',
            self::SETTING_MUDIR_LABEL => 'Pamong',
            self::SETTING_WALI_NAME => null,
            self::SETTING_KEPALA_NAME => null,
            self::SETTING_MUDIR_NAME => null,
        ];
    }

    protected static function normalizeDocumentSettingValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = collect($value)->filter(fn (mixed $item): bool => filled($item))->first();
        }

        if (blank($value)) {
            return null;
        }

        return trim((string) $value);
    }

    /**
     * @return array<int, string>
     */
    protected static function documentEditableSettingKeys(): array
    {
        return [
            self::SETTING_LOGO_PATH,
            self::SETTING_KOP_SITE_NAME,
            self::SETTING_KOP_SUBTITLE,
            self::SETTING_KOP_ADDRESS,
            self::SETTING_KOP_CONTACT,
            self::SETTING_PROLOG,
            self::SETTING_KOTA,
            self::SETTING_WALI_LABEL,
            self::SETTING_KEPALA_LABEL,
            self::SETTING_MUDIR_LABEL,
            self::SETTING_WALI_NAME,
            self::SETTING_KEPALA_NAME,
            self::SETTING_MUDIR_NAME,
        ];
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
