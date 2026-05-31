<?php

namespace App\Filament\Resources\BoardingPencapaianResource\Pages;

use App\Filament\Resources\BoardingPencapaianResource;
use App\Models\BoardingHafalanAssessment;
use App\Models\BoardingHafalanPoint;
use App\Models\BoardingPencapaian;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ManageHafalan extends Page implements HasTable
{
    use InteractsWithRecord;
    use Tables\Concerns\InteractsWithTable;

    protected ?array $filterOptionsCache = null;

    protected static string $resource = BoardingPencapaianResource::class;

    protected static ?string $title = 'Hafalan per Murid';

    protected static ?string $breadcrumb = 'Hafalan';

    protected string $view = 'filament.resources.boarding-pencapaian-resource.pages.manage-hafalan';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->getRecord()->loadMissing('siswa:id,nama,rombel_saat_ini');

        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canAccess(), 403);
    }

    public function getRecord(): BoardingPencapaian
    {
        /** @var BoardingPencapaian $record */
        $record = $this->record;

        return $record;
    }

    protected function canManageAssessments(): bool
    {
        return static::getResource()::canEdit($this->getRecord());
    }

    protected function materiLabel(string $materiKey): string
    {
        return BoardingHafalanPoint::materiLabel($materiKey);
    }

    protected function jenisLabel(?string $jenis): string
    {
        return BoardingHafalanPoint::jenisLabel($jenis);
    }

    protected function hafalanMateriOrderSql(string $column = 'boarding_hafalan_points.materi_key'): string
    {
        $orderedKeys = array_keys(BoardingHafalanPoint::MATERI_OPTIONS);
        $materiTambahanIndex = array_search(BoardingHafalanPoint::MATERI_TAMBAHAN_HAFALAN_KEY, $orderedKeys, true);
        $aliases = [
            'materi_tambahan' => $materiTambahanIndex,
            'seleksi_saringan' => $materiTambahanIndex,
        ];

        $cases = [];

        foreach ($orderedKeys as $index => $materiKey) {
            $cases[] = "WHEN {$column} = '".str_replace("'", "''", $materiKey)."' THEN {$index}";
        }

        foreach ($aliases as $materiKey => $index) {
            if ($index === false) {
                continue;
            }

            $cases[] = "WHEN {$column} = '".str_replace("'", "''", $materiKey)."' THEN {$index}";
        }

        return 'CASE '.implode(' ', $cases).' ELSE 999 END';
    }

    protected function hafalanPointBaseQuery(int|string $pencapaianId): Builder
    {
        return BoardingHafalanPoint::query()
            ->whereIn('boarding_hafalan_points.jenis', BoardingHafalanPoint::hafalanJenis())
            ->where(function (Builder $query) use ($pencapaianId): void {
                $query
                    ->where('boarding_hafalan_points.is_active', true)
                    ->orWhereExists(function ($assessmentQuery) use ($pencapaianId): void {
                        $assessmentQuery
                            ->select(DB::raw(1))
                            ->from('boarding_hafalan_assessments')
                            ->whereColumn('boarding_hafalan_assessments.boarding_hafalan_point_id', 'boarding_hafalan_points.id')
                            ->where('boarding_hafalan_assessments.boarding_pencapaian_id', $pencapaianId);
                    });
            });
    }

    protected function materiOptions(): array
    {
        return $this->filterOptions()['materi'];
    }

    protected function jenisOptions(): array
    {
        return $this->filterOptions()['jenis'];
    }

    protected function filterOptions(): array
    {
        if ($this->filterOptionsCache !== null) {
            return $this->filterOptionsCache;
        }

        $materiOptions = [];
        $jenisOptions = [];

        (clone $this->hafalanPointBaseQuery($this->getRecord()->getKey()))
            ->select('materi_key', 'jenis')
            ->distinct()
            ->orderByRaw($this->hafalanMateriOrderSql())
            ->orderBy('materi_key')
            ->orderBy('jenis')
            ->get()
            ->each(function (BoardingHafalanPoint $point) use (&$materiOptions, &$jenisOptions): void {
                if (filled($point->materi_key)) {
                    $materiOptions[$point->materi_key] = $this->materiLabel((string) $point->materi_key);
                }

                if (filled($point->jenis)) {
                    $jenisOptions[$point->jenis] = $this->jenisLabel((string) $point->jenis);
                }
            });

        return $this->filterOptionsCache = [
            'materi' => $materiOptions,
            'jenis' => $jenisOptions,
        ];
    }

    protected function assessmentStatusLabel(BoardingHafalanPoint $record): string
    {
        if (! $record->is_active) {
            return 'Nonaktif';
        }

        return filled($record->getAttribute('assessment_id')) ? 'Dinilai' : 'Belum dinilai';
    }

    protected function assessmentStatusColor(BoardingHafalanPoint $record): string
    {
        if (! $record->is_active) {
            return 'gray';
        }

        return filled($record->getAttribute('assessment_id')) ? 'success' : 'warning';
    }

    protected function assessmentDateLabel(mixed $state): string
    {
        if (! filled($state)) {
            return '-';
        }

        return Carbon::parse($state)->translatedFormat('d M Y');
    }

    protected function assessmentReviewerLabel(BoardingHafalanPoint $record): string
    {
        $name = $record->getAttribute('assessment_reviewer_user_name')
            ?: $record->getAttribute('assessment_reviewer_name');

        return filled($name) ? (string) $name : '-';
    }

    protected function assessmentScoreLabel(BoardingHafalanPoint $record): string
    {
        $score = $record->getAttribute('assessment_score');

        return filled($score) ? ((int) $score).' / 100' : 'Belum ada';
    }

    protected function assessmentScoreColor(BoardingHafalanPoint $record): string
    {
        $score = $record->getAttribute('assessment_score');

        if (! filled($score)) {
            return 'gray';
        }

        return match (true) {
            (int) $score >= 80 => 'success',
            (int) $score >= 60 => 'warning',
            default => 'danger',
        };
    }

    public function table(Table $table): Table
    {
        $pencapaianId = $this->getRecord()->getKey();

        return $table
            ->query(fn (): Builder => $this->hafalanPointBaseQuery($pencapaianId)
                ->leftJoin('boarding_hafalan_assessments as assessments', function ($join) use ($pencapaianId): void {
                    $join
                        ->on('assessments.boarding_hafalan_point_id', '=', 'boarding_hafalan_points.id')
                        ->where('assessments.boarding_pencapaian_id', '=', $pencapaianId);
                })
                ->leftJoin('users as assessment_reviewers', 'assessment_reviewers.id', '=', 'assessments.reviewer_user_id')
                ->select([
                    'boarding_hafalan_points.id',
                    'boarding_hafalan_points.nama_point',
                    'boarding_hafalan_points.materi_key',
                    'boarding_hafalan_points.jenis',
                    'boarding_hafalan_points.urutan',
                    'boarding_hafalan_points.is_active',
                ])
                ->addSelect([
                    DB::raw('assessments.id as assessment_id'),
                    DB::raw('assessments.assessed_at as assessment_assessed_at'),
                    DB::raw('assessments.score as assessment_score'),
                    DB::raw('assessments.reviewer_user_id as assessment_reviewer_user_id'),
                    DB::raw('assessments.reviewer_name as assessment_reviewer_name'),
                    DB::raw('assessment_reviewers.name as assessment_reviewer_user_name'),
                ]))
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw($this->hafalanMateriOrderSql())
                ->orderBy('boarding_hafalan_points.materi_key')
                ->orderByRaw('CASE WHEN boarding_hafalan_points.is_active = 1 THEN 0 ELSE 1 END')
                ->orderBy('jenis')
                ->orderBy('urutan')
                ->orderBy('boarding_hafalan_points.id'))
            ->defaultPaginationPageOption(200)
            ->paginated([25, 50, 100, 200])
            ->filters([
                Tables\Filters\SelectFilter::make('materi_key')
                    ->label('Materi')
                    ->options(fn (): array => $this->materiOptions()),
                Tables\Filters\SelectFilter::make('jenis')
                    ->label('Jenis Hafalan')
                    ->options(fn (): array => $this->jenisOptions()),
            ])
            ->groups([
                Group::make('materi_key')
                    ->label('Materi')
                    ->collapsible()
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw($this->hafalanMateriOrderSql()." {$direction}")
                        ->orderBy('boarding_hafalan_points.materi_key', $direction))
                    ->getTitleFromRecordUsing(fn (BoardingHafalanPoint $record): string => $this->materiLabel((string) $record->materi_key)),
            ])
            ->defaultGroup('materi_key')
            ->collapsedGroupsByDefault()
            ->columns([
                Tables\Columns\TextColumn::make('nama_point')
                    ->label('Hafalan')
                    ->wrap()
                    ->searchable()
                    ->description(function (BoardingHafalanPoint $record): string {
                        $segments = [
                            $this->jenisLabel($record->jenis),
                            'Urutan '.(int) $record->urutan,
                        ];

                        if (! $record->is_active) {
                            $segments[] = 'Point nonaktif';
                        }

                        return implode(' | ', $segments);
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (BoardingHafalanPoint $record): string => $this->assessmentStatusLabel($record))
                    ->color(fn (BoardingHafalanPoint $record): string => $this->assessmentStatusColor($record))
                    ->description(function (BoardingHafalanPoint $record): ?string {
                        if (! filled($record->getAttribute('assessment_assessed_at'))) {
                            return null;
                        }

                        return 'Dinilai '.$this->assessmentDateLabel($record->getAttribute('assessment_assessed_at'));
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('assessment_score')
                    ->label('Nilai')
                    ->badge()
                    ->alignCenter()
                    ->state(fn (BoardingHafalanPoint $record): string => $this->assessmentScoreLabel($record))
                    ->color(fn (BoardingHafalanPoint $record): string => $this->assessmentScoreColor($record))
                    ->description(fn (BoardingHafalanPoint $record): string => 'Penyimak: '.$this->assessmentReviewerLabel($record))
                    ->wrap()
                    ->toggleable(),
            ])
            ->actions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->slideOver()
                    ->modalWidth('xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalHeading(fn (BoardingHafalanPoint $record): string => 'Detail Hafalan: '.$record->nama_point)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                Forms\Components\Placeholder::make('materi')
                                    ->label('Materi')
                                    ->content(fn (BoardingHafalanPoint $record): string => $this->materiLabel((string) $record->materi_key)),
                                Forms\Components\Placeholder::make('jenis')
                                    ->label('Jenis')
                                    ->content(fn (BoardingHafalanPoint $record): string => $this->jenisLabel($record->jenis)),
                                Forms\Components\Placeholder::make('urutan')
                                    ->label('Urutan')
                                    ->content(fn (BoardingHafalanPoint $record): string => (string) ((int) $record->urutan)),
                                Forms\Components\Placeholder::make('status')
                                    ->label('Status')
                                    ->content(fn (BoardingHafalanPoint $record): string => $this->assessmentStatusLabel($record)),
                                Forms\Components\Placeholder::make('nilai')
                                    ->label('Nilai')
                                    ->content(fn (BoardingHafalanPoint $record): string => $this->assessmentScoreLabel($record)),
                                Forms\Components\Placeholder::make('dinilai_pada')
                                    ->label('Dinilai Pada')
                                    ->content(fn (BoardingHafalanPoint $record): string => $this->assessmentDateLabel($record->getAttribute('assessment_assessed_at'))),
                                Forms\Components\Placeholder::make('penyimak')
                                    ->label('Penyimak')
                                    ->content(fn (BoardingHafalanPoint $record): string => $this->assessmentReviewerLabel($record)),
                                Forms\Components\Placeholder::make('kondisi_point')
                                    ->label('Kondisi Point')
                                    ->content(fn (BoardingHafalanPoint $record): string => $record->is_active ? 'Aktif' : 'Nonaktif'),
                            ]),
                    ]),
                Action::make('nilai')
                    ->label(function (BoardingHafalanPoint $record): string {
                        return filled($record->getAttribute('assessment_id')) ? 'Ubah Nilai' : 'Nilai';
                    })
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading(function (BoardingHafalanPoint $record): string {
                        return filled($record->getAttribute('assessment_id')) ? 'Ubah nilai hafalan' : 'Nilai hafalan';
                    })
                    ->modalSubmitActionLabel('Simpan')
                    ->modalWidth('lg')
                    ->fillForm(function (BoardingHafalanPoint $record): array {
                        $reviewerName = (string) ($record->getAttribute('assessment_reviewer_name') ?? '');
                        $reviewerUserId = $record->getAttribute('assessment_reviewer_user_id');

                        return [
                            'assessed_at' => $record->getAttribute('assessment_assessed_at')
                                ? Carbon::parse($record->getAttribute('assessment_assessed_at'))->toDateString()
                                : now()->toDateString(),
                            'score' => filled($record->getAttribute('assessment_score'))
                                ? (int) $record->getAttribute('assessment_score')
                                : 0,
                            'reviewer_mode' => filled($reviewerName) && blank($reviewerUserId) ? 'name' : 'user',
                            'reviewer_name' => $reviewerName,
                        ];
                    })
                    ->form([
                        Forms\Components\DatePicker::make('assessed_at')
                            ->label('Tanggal Penilaian')
                            ->required(),
                        Forms\Components\TextInput::make('score')
                            ->label('Nilai (0-100)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required(),
                        Forms\Components\Radio::make('reviewer_mode')
                            ->label('Penyimak')
                            ->options([
                                'user' => 'Akun saya',
                                'name' => 'Input nama penyimak',
                            ])
                            ->default('user')
                            ->live()
                            ->required(),
                        Forms\Components\TextInput::make('reviewer_name')
                            ->label('Nama Penyimak')
                            ->maxLength(100)
                            ->visible(fn (Get $get): bool => $get('reviewer_mode') === 'name')
                            ->required(fn (Get $get): bool => $get('reviewer_mode') === 'name'),
                    ])
                    ->action(function (BoardingHafalanPoint $record, array $data): void {
                        abort_unless($this->canManageAssessments(), 403);

                        if (! $record->is_active) {
                            abort(403);
                        }

                        $reviewerMode = $data['reviewer_mode'] ?? 'user';
                        $reviewerUserId = null;
                        $reviewerName = null;

                        if ($reviewerMode === 'name') {
                            $reviewerName = filled($data['reviewer_name'] ?? null)
                                ? trim((string) $data['reviewer_name'])
                                : null;
                        } else {
                            $reviewerUserId = auth()->id();
                        }

                        BoardingHafalanAssessment::updateOrCreate(
                            [
                                'boarding_pencapaian_id' => $this->getRecord()->getKey(),
                                'boarding_hafalan_point_id' => $record->getKey(),
                            ],
                            [
                                'assessed_at' => $data['assessed_at'],
                                'score' => (int) $data['score'],
                                'reviewer_user_id' => $reviewerUserId,
                                'reviewer_name' => $reviewerName,
                            ]
                        );

                        Notification::make()
                            ->title('Nilai tersimpan.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (BoardingHafalanPoint $record): bool => $this->canManageAssessments() && $record->is_active),
                Action::make('reset')
                    ->label('Reset')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reset nilai hafalan?')
                    ->modalDescription('Nilai yang sudah tersimpan akan dihapus dari pencapaian murid ini.')
                    ->action(function (BoardingHafalanPoint $record): void {
                        abort_unless($this->canManageAssessments(), 403);

                        if (! $record->is_active) {
                            abort(403);
                        }

                        BoardingHafalanAssessment::query()
                            ->where('boarding_pencapaian_id', $this->getRecord()->getKey())
                            ->where('boarding_hafalan_point_id', $record->getKey())
                            ->delete();

                        Notification::make()
                            ->title('Nilai direset.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (BoardingHafalanPoint $record): bool => $this->canManageAssessments()
                        && $record->is_active
                        && filled($record->getAttribute('assessment_id'))),
            ]);
    }
}
