<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoardingStudent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class BoardingPencapaian extends Model
{
    use BelongsToBoardingStudent;

    public const STATUS_OPTIONS = [
        'proses' => 'Proses',
        'tercapai_sebagian' => 'Tercapai Sebagian',
        'tercapai' => 'Tercapai',
    ];

    public const MATERI_RAPOT_SCOPE_BOARDING = 'boarding';

    public const MATERI_RAPOT_SCOPE_MT = 'mt';

    public const MATERI_RAPOT_SCOPE_OPTIONS = [
        self::MATERI_RAPOT_SCOPE_BOARDING => 'Materi Boarding',
        self::MATERI_RAPOT_SCOPE_MT => 'Materi MT',
    ];

    public const UPDATE_CATEGORY_OPTIONS = [
        'surat_quran_tuntas' => 'Quran Tuntas',
        'hafalan_surat' => 'Hafalan Surat',
        'hafalan_doa' => 'Hafalan Doa',
        'hafalan_hadits' => 'Hafalan Hadits',
        'hafalan_lainnya' => 'Hafalan Lainnya',
        'target_berikutnya' => 'Target Berikutnya',
        'catatan_pembinaan' => 'Catatan Pembinaan',
    ];

    public const UPDATE_STATUS_OPTIONS = [
        'progres' => 'Progres',
        'tuntas' => 'Tuntas',
        'butuh_lanjutan' => 'Butuh Lanjutan',
    ];

    public const DETAIL_CATEGORY_OPTIONS = [
        'surat_quran_tuntas' => 'Surat Quran Tuntas',
        'hafalan_surat' => 'Hafalan Surat',
        'hafalan_doa' => 'Hafalan Doa',
        'hafalan_hadits' => 'Hafalan Hadits',
        'ibadah_adab' => 'Ibadah / Adab',
        'bahasa_dan_literasi' => 'Bahasa / Literasi',
        'lainnya' => 'Lainnya',
    ];

    public const DETAIL_STATUS_OPTIONS = [
        'belum_mulai' => 'Belum Mulai',
        'proses' => 'Proses',
        'tuntas' => 'Tuntas',
    ];

    protected static ?string $pamongOwnershipColumn = 'pamong_user_id';

    protected static array $syncedUserIds = [];

    protected $table = 'boarding_pencapaians';

    protected $guarded = [];

    protected $casts = [
        'tanggal_update_terakhir' => 'date',
        'jumlah_surat_dihafal' => 'integer',
        'jumlah_doa_dihafal' => 'integer',
        'jumlah_hadits_dihafal' => 'integer',
        'target_jumlah_surat' => 'integer',
        'target_jumlah_doa' => 'integer',
        'target_jumlah_hadits' => 'integer',
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

            $record->materi_rapot_scope = self::normalizeMateriRapotScope($record->materi_rapot_scope);
        });

        static::saved(function (self $record): void {
            $scopeChanged = $record->wasChanged('materi_rapot_scope');

            if ($record->details()->exists() || $record->updates()->exists()) {
                $record->syncFromProgressData();

                if ($scopeChanged) {
                    $record->syncLinkedRapots(overwriteNarratives: true);
                }

                return;
            }

            $record->syncLinkedRapots(overwriteNarratives: $scopeChanged);
        });
    }

    public static function statusOptions(): array
    {
        return self::STATUS_OPTIONS;
    }

    public static function materiRapotScopeOptions(): array
    {
        return self::MATERI_RAPOT_SCOPE_OPTIONS;
    }

    public static function materiRapotScopeLabel(?string $scope): string
    {
        return self::MATERI_RAPOT_SCOPE_OPTIONS[self::normalizeMateriRapotScope($scope)];
    }

    public static function normalizeMateriRapotScope(?string $scope): string
    {
        return array_key_exists((string) $scope, self::MATERI_RAPOT_SCOPE_OPTIONS)
            ? (string) $scope
            : self::MATERI_RAPOT_SCOPE_BOARDING;
    }

    public static function updateCategoryOptions(): array
    {
        return self::UPDATE_CATEGORY_OPTIONS;
    }

    public static function updateStatusOptions(): array
    {
        return self::UPDATE_STATUS_OPTIONS;
    }

    public static function detailCategoryOptions(): array
    {
        return self::DETAIL_CATEGORY_OPTIONS;
    }

    public static function detailStatusOptions(): array
    {
        return self::DETAIL_STATUS_OPTIONS;
    }

    public static function ensureRecordsForVisibleStudents(mixed $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        $userKey = (string) ($user->getKey() ?? spl_object_id($user));

        if (isset(static::$syncedUserIds[$userKey])) {
            return;
        }

        static::$syncedUserIds[$userKey] = true;

        $studentIds = DataSiswa::applyVisibleScope(DataSiswa::query(), $user)
            ->orderBy('nama')
            ->pluck('id');

        if ($studentIds->isEmpty()) {
            return;
        }

        $existingStudentIds = self::query()
            ->whereIn('siswa_id', $studentIds)
            ->pluck('siswa_id');

        $missingStudentIds = $studentIds->diff($existingStudentIds);

        if ($missingStudentIds->isEmpty()) {
            return;
        }

        $now = now();
        $pamongUserId = $user->isBoardingPamong() ? $user->getKey() : null;

        self::query()->insertOrIgnore(
            $missingStudentIds
                ->map(fn (mixed $siswaId): array => [
                    'siswa_id' => (int) $siswaId,
                    'pamong_user_id' => $pamongUserId,
                    'status_pencapaian' => 'proses',
                    'materi_rapot_scope' => self::MATERI_RAPOT_SCOPE_BOARDING,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all()
        );
    }

    public function scopeWithFilledTargetData(Builder $query): Builder
    {
        $textColumns = [
            'surat_quran_tuntas',
            'hadits_tuntas',
            'hafalan_surat',
            'hafalan_doa',
            'hafalan_lainnya',
            'target_berikutnya',
            'catatan',
        ];

        return $query->where(function (Builder $query) use ($textColumns): void {
            $query
                ->whereHas('details')
                ->orWhereHas('updates')
                ->orWhereHas('hafalanAssessments')
                ->orWhereHas('bacaanAssessments')
                ->orWhereHas(
                    'maknaProgresses',
                    fn (Builder $maknaQuery): Builder => $maknaQuery->whereIn('status', ['sebagian', 'khatam'])
                )
                ->orWhereHas('mtProgresses', fn (Builder $mtQuery): Builder => $mtQuery->filled())
                ->orWhereHas('materiProgresses', fn (Builder $materiQuery): Builder => $materiQuery->filled())
                ->orWhere('status_pencapaian', '!=', 'proses')
                ->orWhere('target_jumlah_surat', '>', 0)
                ->orWhere('target_jumlah_doa', '>', 0)
                ->orWhere('target_jumlah_hadits', '>', 0)
                ->orWhere('jumlah_surat_dihafal', '>', 0)
                ->orWhere('jumlah_doa_dihafal', '>', 0)
                ->orWhere('jumlah_hadits_dihafal', '>', 0);

            foreach ($textColumns as $column) {
                $query->orWhere(function (Builder $textQuery) use ($column): void {
                    $textQuery
                        ->whereNotNull($column)
                        ->where($column, '!=', '');
                });
            }
        });
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(DataSiswa::class, 'siswa_id');
    }

    public function pamongUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pamong_user_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(BoardingPencapaianDetail::class, 'boarding_pencapaian_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(BoardingPencapaianUpdate::class, 'boarding_pencapaian_id');
    }

    public function hafalanAssessments(): HasMany
    {
        return $this->hasMany(BoardingHafalanAssessment::class, 'boarding_pencapaian_id');
    }

    public function maknaProgresses(): HasMany
    {
        return $this->hasMany(BoardingMaknaProgress::class, 'boarding_pencapaian_id');
    }

    public function mtProgresses(): HasMany
    {
        return $this->hasMany(BoardingMtProgress::class, 'boarding_pencapaian_id');
    }

    public function materiProgresses(): HasMany
    {
        return $this->hasMany(BoardingMateriProgress::class, 'boarding_pencapaian_id');
    }

    public function bacaanAssessments(): HasMany
    {
        return $this->hasMany(BoardingBacaanAssessment::class, 'boarding_pencapaian_id');
    }

    public function syncFromHafalanAssessments(): void
    {
        /** @var Collection<int, BoardingHafalanAssessment> $assessments */
        $assessments = $this->hafalanAssessments()
            ->with(['point:id,jenis,nama_point,materi_key,urutan,is_active'])
            ->get()
            ->filter(fn (BoardingHafalanAssessment $assessment): bool => (bool) $assessment->point?->is_active
                && in_array((string) $assessment->point?->jenis, BoardingHafalanPoint::hafalanJenis(), true))
            ->sortBy(fn (BoardingHafalanAssessment $assessment): string => sprintf(
                '%s|%05d|%05d',
                (string) ($assessment->point?->materi_key ?? ''),
                (int) ($assessment->point?->urutan ?? 0),
                (int) ($assessment->point?->id ?? 0),
            ))
            ->values();

        $suratAssessments = $assessments->filter(fn (BoardingHafalanAssessment $assessment): bool => $assessment->point?->jenis === 'surat');
        $doaAssessments = $assessments->filter(fn (BoardingHafalanAssessment $assessment): bool => $assessment->point?->jenis === 'doa');
        $dalilAssessments = $assessments->filter(fn (BoardingHafalanAssessment $assessment): bool => $assessment->point?->jenis === 'dalil');
        $totalActivePoints = BoardingHafalanPoint::query()
            ->where('is_active', true)
            ->whereIn('jenis', BoardingHafalanPoint::hafalanJenis())
            ->count();
        $completedPoints = $assessments->count();
        $latestAssessmentDate = optional($assessments->sortByDesc('assessed_at')->first())->assessed_at;

        $status = match (true) {
            $completedPoints === 0 => 'proses',
            $totalActivePoints > 0 && $completedPoints >= $totalActivePoints => 'tercapai',
            default => 'tercapai_sebagian',
        };

        $this->forceFill([
            'tanggal_update_terakhir' => $latestAssessmentDate,
            'status_pencapaian' => $status,
            'surat_quran_tuntas' => self::stringifyHafalanAssessments($suratAssessments),
            'hadits_tuntas' => null,
            'hafalan_surat' => self::stringifyHafalanAssessments($suratAssessments),
            'hafalan_doa' => self::stringifyHafalanAssessments($doaAssessments),
            'hafalan_lainnya' => self::stringifyHafalanAssessments($dalilAssessments),
            'jumlah_surat_dihafal' => $suratAssessments->count(),
            'jumlah_doa_dihafal' => $doaAssessments->count(),
            'jumlah_hadits_dihafal' => 0,
        ])->saveQuietly();

        $this->syncLatestBoardingModuleDate();
    }

    public function syncFromProgressData(): void
    {
        /** @var Collection<int, BoardingPencapaianDetail> $details */
        $details = $this->details()
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();

        /** @var Collection<int, BoardingPencapaianUpdate> $updates */
        $updates = $this->updates()
            ->orderBy('tanggal_update')
            ->orderBy('id')
            ->get();

        if ($details->isEmpty() && $updates->isEmpty()) {
            return;
        }

        if ($details->isNotEmpty()) {
            $this->syncFromDetailsAndUpdates($details, $updates);

            return;
        }

        $this->syncFromUpdatesOnly($updates);
    }

    /**
     * @param  Collection<int, BoardingPencapaianDetail>  $details
     * @param  Collection<int, BoardingPencapaianUpdate>  $updates
     */
    protected function syncFromDetailsAndUpdates(Collection $details, Collection $updates): void
    {
        $suratCategories = ['surat_quran_tuntas', 'hafalan_surat'];
        $otherProgressCategories = ['ibadah_adab', 'bahasa_dan_literasi', 'lainnya'];

        $targetSurat = (int) $details->whereIn('kategori_detail', $suratCategories)->sum('target_nilai');
        $targetDoa = (int) $details->where('kategori_detail', 'hafalan_doa')->sum('target_nilai');
        $targetHadits = (int) $details->where('kategori_detail', 'hafalan_hadits')->sum('target_nilai');

        $jumlahSurat = (int) $details->whereIn('kategori_detail', $suratCategories)->sum('capaian_nilai');
        $jumlahDoa = (int) $details->where('kategori_detail', 'hafalan_doa')->sum('capaian_nilai');
        $jumlahHadits = (int) $details->where('kategori_detail', 'hafalan_hadits')->sum('capaian_nilai');

        $attributes = [
            'target_jumlah_surat' => $targetSurat,
            'target_jumlah_doa' => $targetDoa,
            'target_jumlah_hadits' => $targetHadits,
            'jumlah_surat_dihafal' => $jumlahSurat,
            'jumlah_doa_dihafal' => $jumlahDoa,
            'jumlah_hadits_dihafal' => $jumlahHadits,
            'tanggal_update_terakhir' => $this->resolveLatestProgressDate($details, $updates),
            'surat_quran_tuntas' => self::stringifyDetails($details->where('kategori_detail', 'surat_quran_tuntas')),
            'hadits_tuntas' => self::stringifyDetails($details->where('kategori_detail', 'hafalan_hadits')->where('status_detail', 'tuntas')),
            'hafalan_surat' => self::stringifyDetails($details->where('kategori_detail', 'hafalan_surat')),
            'hafalan_doa' => self::stringifyDetails($details->where('kategori_detail', 'hafalan_doa')),
            'hafalan_lainnya' => self::stringifyDetails($details->whereIn('kategori_detail', $otherProgressCategories)),
            'target_berikutnya' => self::stringifyUpdates($updates->where('kategori_update', 'target_berikutnya')),
            'catatan' => trim(implode(PHP_EOL.PHP_EOL, array_filter([
                self::stringifyDetails($details->where('status_detail', '!=', 'tuntas')),
                self::stringifyUpdates($updates->where('kategori_update', 'catatan_pembinaan')),
            ]))) ?: null,
        ];

        $totalTarget = (int) $details->sum('target_nilai');
        $totalAktual = (int) $details->sum('capaian_nilai');
        $hasOpenFollowUp = $updates->contains(fn (BoardingPencapaianUpdate $update): bool => $update->status_update === 'butuh_lanjutan');
        $hasProgress = $details->contains(fn (BoardingPencapaianDetail $detail): bool => (int) $detail->capaian_nilai > 0 || $detail->status_detail !== 'belum_mulai');
        $allTuntas = $details->isNotEmpty() && $details->every(fn (BoardingPencapaianDetail $detail): bool => $detail->status_detail === 'tuntas');

        $attributes['status_pencapaian'] = match (true) {
            $allTuntas && ! $hasOpenFollowUp => 'tercapai',
            $hasProgress || $totalAktual > 0 => 'tercapai_sebagian',
            $totalTarget === 0 && $updates->isNotEmpty() => 'tercapai_sebagian',
            default => 'proses',
        };

        $this->forceFill($attributes)->saveQuietly();
        $this->syncLatestBoardingModuleDate();
    }

    /**
     * @param  Collection<int, BoardingPencapaianUpdate>  $updates
     */
    protected function syncFromUpdatesOnly(Collection $updates): void
    {
        if ($updates->isEmpty()) {
            return;
        }

        $jumlahSurat = (int) $updates->where('kategori_update', 'hafalan_surat')->sum('jumlah_tambahan');
        $jumlahDoa = (int) $updates->where('kategori_update', 'hafalan_doa')->sum('jumlah_tambahan');
        $jumlahHadits = (int) $updates->where('kategori_update', 'hafalan_hadits')->sum('jumlah_tambahan');

        $attributes = [
            'jumlah_surat_dihafal' => $jumlahSurat,
            'jumlah_doa_dihafal' => $jumlahDoa,
            'jumlah_hadits_dihafal' => $jumlahHadits,
            'tanggal_update_terakhir' => optional($updates->sortByDesc('tanggal_update')->first())->tanggal_update,
            'surat_quran_tuntas' => self::stringifyUpdates($updates->where('kategori_update', 'surat_quran_tuntas')),
            'hadits_tuntas' => self::stringifyUpdates($updates->where('kategori_update', 'hafalan_hadits')),
            'hafalan_surat' => self::stringifyUpdates($updates->where('kategori_update', 'hafalan_surat')),
            'hafalan_doa' => self::stringifyUpdates($updates->where('kategori_update', 'hafalan_doa')),
            'hafalan_lainnya' => self::stringifyUpdates($updates->where('kategori_update', 'hafalan_lainnya')),
            'target_berikutnya' => self::stringifyUpdates($updates->where('kategori_update', 'target_berikutnya')),
            'catatan' => self::stringifyUpdates($updates->where('kategori_update', 'catatan_pembinaan')),
        ];

        $totalTarget = (int) $this->target_jumlah_surat + (int) $this->target_jumlah_doa + (int) $this->target_jumlah_hadits;
        $totalAktual = $jumlahSurat + $jumlahDoa + $jumlahHadits;
        $hasOpenFollowUp = $updates->contains(fn (BoardingPencapaianUpdate $update): bool => $update->status_update === 'butuh_lanjutan');

        $attributes['status_pencapaian'] = match (true) {
            $totalAktual === 0 && ! $updates->contains(fn (BoardingPencapaianUpdate $update): bool => filled($update->judul_capaian)) => 'proses',
            $totalTarget > 0 && $totalAktual >= $totalTarget && ! $hasOpenFollowUp => 'tercapai',
            $totalTarget === 0 && $updates->every(fn (BoardingPencapaianUpdate $update): bool => $update->status_update === 'tuntas') => 'tercapai',
            default => 'tercapai_sebagian',
        };

        $this->forceFill($attributes)->saveQuietly();
        $this->syncLatestBoardingModuleDate();
    }

    public function syncLatestBoardingModuleDate(): void
    {
        $candidates = collect([
            $this->hafalanAssessments()->max('assessed_at'),
            $this->bacaanAssessments()->max('assessed_at'),
            $this->updates()->max('tanggal_update'),
            $this->details()->max('tuntas_at'),
            $this->details()->max('updated_at'),
            $this->maknaProgresses()->max('updated_at'),
            $this->mtProgresses()->filled()->max('updated_at'),
            $this->materiProgresses()->filled()->max('updated_at'),
        ])
            ->filter()
            ->map(fn ($value): Carbon => Carbon::parse($value))
            ->sortDesc()
            ->values();

        $latest = $candidates->first();

        $this->forceFill([
            'tanggal_update_terakhir' => $latest?->toDateString(),
        ])->saveQuietly();

        $this->syncLinkedRapots();
    }

    public function syncLinkedRapots(bool $overwriteNarratives = false): int
    {
        if (blank($this->siswa_id)) {
            return 0;
        }

        $rapots = BoardingRapot::query()
            ->where('siswa_id', $this->siswa_id)
            ->when(
                filled($this->pamong_user_id),
                fn (Builder $query): Builder => $query->where(function (Builder $ownerQuery): void {
                    $ownerQuery
                        ->whereNull('pamong_user_id')
                        ->orWhere('pamong_user_id', $this->pamong_user_id);
                })
            )
            ->get();

        $rapots->each(fn (BoardingRapot $rapot) => $rapot->syncFromSources(
            overwriteNarratives: $overwriteNarratives,
            overwritePencapaianSummary: true,
        ));

        return $rapots->count();
    }

    /**
     * @param  Collection<int, BoardingPencapaianDetail>  $details
     */
    protected function resolveLatestProgressDate(Collection $details, Collection $updates)
    {
        $detailDate = optional($details->sortByDesc(function (BoardingPencapaianDetail $detail): string {
            return (string) ($detail->tuntas_at?->toDateString() ?? $detail->updated_at?->toDateString());
        })->first())->tuntas_at;

        $updateDate = optional($updates->sortByDesc('tanggal_update')->first())->tanggal_update;

        return $detailDate && $updateDate
            ? ($detailDate->greaterThan($updateDate) ? $detailDate : $updateDate)
            : ($detailDate ?: $updateDate);
    }

    /**
     * @param  Collection<int, BoardingPencapaianDetail>  $details
     */
    protected static function stringifyDetails(Collection $details): ?string
    {
        $content = $details
            ->map(function (BoardingPencapaianDetail $detail): string {
                $segments = [$detail->nama_target];
                $satuan = filled($detail->satuan) ? ' '.$detail->satuan : '';
                $segments[] = $detail->capaian_nilai.'/'.$detail->target_nilai.$satuan;
                $segments[] = self::detailStatusOptions()[$detail->status_detail] ?? $detail->status_detail;

                if (filled($detail->detail)) {
                    $segments[] = trim((string) $detail->detail);
                }

                return implode(' | ', array_filter($segments));
            })
            ->filter()
            ->implode(PHP_EOL);

        return $content !== '' ? $content : null;
    }

    /**
     * @param  Collection<int, BoardingPencapaianUpdate>  $updates
     */
    protected static function stringifyUpdates(Collection $updates): ?string
    {
        $content = $updates
            ->map(function (BoardingPencapaianUpdate $update): string {
                $segments = [$update->judul_capaian];

                if ((int) $update->jumlah_tambahan > 0) {
                    $segments[] = '+'.$update->jumlah_tambahan;
                }

                if (filled($update->detail)) {
                    $segments[] = trim((string) $update->detail);
                }

                return implode(' | ', array_filter($segments));
            })
            ->filter()
            ->implode(PHP_EOL);

        return $content !== '' ? $content : null;
    }

    /**
     * @param  Collection<int, BoardingHafalanAssessment>  $assessments
     */
    protected static function stringifyHafalanAssessments(Collection $assessments): ?string
    {
        $content = $assessments
            ->map(fn (BoardingHafalanAssessment $assessment): ?string => $assessment->point?->nama_point)
            ->filter()
            ->implode(PHP_EOL);

        return $content !== '' ? $content : null;
    }
}
