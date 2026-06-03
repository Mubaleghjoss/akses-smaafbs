<?php

namespace App\Filament\Resources\BoardingPencapaianResource\Pages;

use App\Filament\Resources\BoardingPencapaianResource;
use App\Models\BoardingBacaanAssessment;
use App\Models\BoardingPencapaian;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ManageBacaan extends Page implements HasTable
{
    use InteractsWithRecord;
    use Tables\Concerns\InteractsWithTable;

    protected ?array $summaryMetricsCache = null;

    protected static string $resource = BoardingPencapaianResource::class;

    protected static ?string $title = 'Riwayat Bacaan';

    protected static ?string $breadcrumb = 'Bacaan';

    protected string $view = 'filament.resources.boarding-pencapaian-resource.pages.manage-bacaan';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->getRecord()->loadMissing('siswa:id,nama,rombel_saat_ini');

        abort_unless(static::getResource()::canAccess(), 403);
    }

    public function getRecord(): BoardingPencapaian
    {
        /** @var BoardingPencapaian $record */
        $record = $this->record;

        return $record;
    }

    protected function canManageBacaan(): bool
    {
        return static::getResource()::canEdit($this->getRecord());
    }

    protected function forgetSummaryMetrics(): void
    {
        $this->summaryMetricsCache = null;
    }

    public function getViewData(): array
    {
        if ($this->summaryMetricsCache === null) {
            $baseQuery = BoardingBacaanAssessment::query()
                ->where('boarding_pencapaian_id', $this->getRecord()->getKey());

            $summary = (clone $baseQuery)
                ->selectRaw('count(*) as total_sessions, max(assessed_at) as latest_assessed_at')
                ->first();

            $latest = (clone $baseQuery)
                ->select([
                    'id',
                    'boarding_pencapaian_id',
                    'assessed_at',
                    'pp_grade',
                    'kl_grade',
                    'tj_grade',
                    'mj_grade',
                    'kelas_bacaan',
                    'reviewer_user_id',
                    'reviewer_name',
                ])
                ->with('reviewerUser:id,name')
                ->orderByDesc('assessed_at')
                ->orderByDesc('id')
                ->first();

            $this->summaryMetricsCache = [
                'total_sessions' => (int) ($summary?->total_sessions ?? 0),
                'latest_date' => filled($summary?->latest_assessed_at) ? Carbon::parse($summary->latest_assessed_at)->translatedFormat('d M Y') : '-',
                'latest_reviewer' => $latest ? $this->reviewerLabel($latest) : '-',
                'latest_grades' => $latest ? $this->gradeSummary($latest) : 'Belum ada riwayat',
                'latest_class' => BoardingBacaanAssessment::classLabel($latest?->kelas_bacaan),
            ];
        }

        return [
            'summaryMetrics' => $this->summaryMetricsCache,
        ];
    }

    protected function gradeColor(?string $grade): string
    {
        return match ($grade) {
            'A' => 'success',
            'B' => 'info',
            'C' => 'warning',
            'D' => 'danger',
            default => 'gray',
        };
    }

    protected function reviewerLabel(BoardingBacaanAssessment $record): string
    {
        return $record->reviewerUser?->name
            ?: ($record->reviewer_name ?: '-');
    }

    protected function gradeSummary(BoardingBacaanAssessment $record): string
    {
        return implode(' | ', [
            'PP '.$record->pp_grade,
            'KL '.$record->kl_grade,
            'TJ '.$record->tj_grade,
            'MJ '.$record->mj_grade,
        ]);
    }

    protected function reviewerPayload(array $data): array
    {
        $reviewerMode = $data['reviewer_mode'] ?? 'user';

        return [
            'reviewer_user_id' => $reviewerMode === 'user' ? ($data['reviewer_user_id'] ?? auth()->id()) : null,
            'reviewer_name' => $reviewerMode === 'name'
                ? (filled($data['reviewer_name'] ?? null) ? trim((string) $data['reviewer_name']) : null)
                : null,
        ];
    }

    protected function bacaanFormSchema(): array
    {
        return [
            Forms\Components\DatePicker::make('assessed_at')
                ->label('Tanggal Baca')
                ->default(now()->toDateString())
                ->required(),
            Forms\Components\Select::make('kelas_bacaan')
                ->label("Kelas Bacaan Qur'an")
                ->options(BoardingBacaanAssessment::classOptions())
                ->native(false)
                ->selectablePlaceholder(false)
                ->required(),
            Grid::make(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\Select::make('pp_grade')
                        ->label('Nilai Panjang Pendek (PP)')
                        ->options(BoardingBacaanAssessment::gradeOptions())
                        ->required(),
                    Forms\Components\Select::make('kl_grade')
                        ->label('Nilai Kelancaran (KL)')
                        ->options(BoardingBacaanAssessment::gradeOptions())
                        ->required(),
                    Forms\Components\Select::make('tj_grade')
                        ->label('Nilai Tajwid (TJ)')
                        ->options(BoardingBacaanAssessment::gradeOptions())
                        ->required(),
                    Forms\Components\Select::make('mj_grade')
                        ->label('Nilai Mahroj (MJ)')
                        ->options(BoardingBacaanAssessment::gradeOptions())
                        ->required(),
                ]),
            Forms\Components\Radio::make('reviewer_mode')
                ->label('Disimak Oleh')
                ->options([
                    'user' => 'Tautkan akun',
                    'name' => 'Tulis manual',
                ])
                ->default('user')
                ->live()
                ->required(),
            Forms\Components\Select::make('reviewer_user_id')
                ->label('Akun Penyimak')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => User::searchNameOptions($search))
                ->getOptionLabelUsing(fn ($value): ?string => User::resolveNameOptionLabel($value))
                ->default(fn (): ?int => auth()->id())
                ->visible(fn (Get $get): bool => ($get('reviewer_mode') ?? 'user') === 'user')
                ->required(fn (Get $get): bool => ($get('reviewer_mode') ?? 'user') === 'user'),
            Forms\Components\TextInput::make('reviewer_name')
                ->label('Nama Penyimak')
                ->maxLength(100)
                ->visible(fn (Get $get): bool => ($get('reviewer_mode') ?? 'user') === 'name')
                ->required(fn (Get $get): bool => ($get('reviewer_mode') ?? 'user') === 'name'),
            Forms\Components\Textarea::make('notes')
                ->label('Catatan')
                ->rows(4)
                ->columnSpanFull(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => BoardingBacaanAssessment::query()
                ->where('boarding_pencapaian_id', $this->getRecord()->getKey())
                ->select([
                    'id',
                    'boarding_pencapaian_id',
                    'assessed_at',
                    'pp_grade',
                    'kl_grade',
                    'tj_grade',
                    'mj_grade',
                    'kelas_bacaan',
                    'reviewer_user_id',
                    'reviewer_name',
                    'notes',
                ])
                ->with('reviewerUser:id,name'))
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByDesc('assessed_at')
                ->orderByDesc('id'))
            ->paginated(false)
            ->searchPlaceholder('Cari penyimak atau catatan bacaan...')
            ->emptyStateHeading('Belum ada riwayat bacaan')
            ->emptyStateDescription('Tambah nilai bacaan pertama.')
            ->headerActions([
                Action::make('exportBacaan')
                    ->label('Export Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->url(fn (): string => route('admin.boarding-pencapaians.bacaan.export', $this->getRecord()))
                    ->openUrlInNewTab(),
                Action::make('tambahBacaan')
                    ->label('Tambah')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->modalHeading('Tambah Penilaian Bacaan')
                    ->modalSubmitActionLabel('Simpan')
                    ->modalWidth('3xl')
                    ->form($this->bacaanFormSchema())
                    ->action(function (array $data): void {
                        abort_unless($this->canManageBacaan(), 403);
                        $reviewerPayload = $this->reviewerPayload($data);

                        BoardingBacaanAssessment::query()->create([
                            'boarding_pencapaian_id' => $this->getRecord()->getKey(),
                            'assessed_at' => $data['assessed_at'],
                            'pp_grade' => $data['pp_grade'],
                            'kl_grade' => $data['kl_grade'],
                            'tj_grade' => $data['tj_grade'],
                            'mj_grade' => $data['mj_grade'],
                            'kelas_bacaan' => $data['kelas_bacaan'],
                            'reviewer_user_id' => $reviewerPayload['reviewer_user_id'],
                            'reviewer_name' => $reviewerPayload['reviewer_name'],
                            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                        ]);

                        $this->forgetSummaryMetrics();

                        Notification::make()
                            ->title('Penilaian bacaan tersimpan.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => $this->canManageBacaan()),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('assessed_at')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\SelectColumn::make('kelas_bacaan')
                    ->label('Kelas')
                    ->options(BoardingBacaanAssessment::classOptions())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->rules(['required', 'string', 'max:10'])
                    ->disabled(fn (): bool => ! $this->canManageBacaan()),
                Tables\Columns\SelectColumn::make('pp_grade')
                    ->label('PP')
                    ->options(BoardingBacaanAssessment::gradeOptions())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->rules(['required', 'string', 'max:1'])
                    ->disabled(fn (): bool => ! $this->canManageBacaan()),
                Tables\Columns\SelectColumn::make('kl_grade')
                    ->label('KL')
                    ->options(BoardingBacaanAssessment::gradeOptions())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->rules(['required', 'string', 'max:1'])
                    ->disabled(fn (): bool => ! $this->canManageBacaan()),
                Tables\Columns\SelectColumn::make('tj_grade')
                    ->label('TJ')
                    ->options(BoardingBacaanAssessment::gradeOptions())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->rules(['required', 'string', 'max:1'])
                    ->disabled(fn (): bool => ! $this->canManageBacaan()),
                Tables\Columns\SelectColumn::make('mj_grade')
                    ->label('MJ')
                    ->options(BoardingBacaanAssessment::gradeOptions())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->rules(['required', 'string', 'max:1'])
                    ->disabled(fn (): bool => ! $this->canManageBacaan()),
                Tables\Columns\TextColumn::make('reviewer')
                    ->label('Penyimak')
                    ->state(fn (BoardingBacaanAssessment $record): string => $this->reviewerLabel($record))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $reviewerQuery) use ($search): void {
                            $reviewerQuery
                                ->where('reviewer_name', 'like', "%{$search}%")
                                ->orWhereHas('reviewerUser', fn (Builder $userQuery): Builder => $userQuery->where('name', 'like', "%{$search}%"));
                        });
                    })
                    ->wrap()
                    ->visibleFrom('md'),
                Tables\Columns\TextInputColumn::make('notes')
                    ->label('Catatan')
                    ->placeholder('-')
                    ->rules(['nullable', 'string', 'max:65535'])
                    ->disabled(fn (): bool => ! $this->canManageBacaan()),
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
                    ->modalHeading(fn (BoardingBacaanAssessment $record): string => 'Riwayat Bacaan: '.($record->assessed_at?->translatedFormat('d M Y') ?? '-'))
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                Forms\Components\Placeholder::make('tanggal_baca')
                                    ->label('Tanggal Baca')
                                    ->content(fn (BoardingBacaanAssessment $record): string => $record->assessed_at?->translatedFormat('d M Y') ?? '-'),
                                Forms\Components\Placeholder::make('penyimak')
                                    ->label('Penyimak')
                                    ->content(fn (BoardingBacaanAssessment $record): string => $this->reviewerLabel($record)),
                                Forms\Components\Placeholder::make('kelas_bacaan')
                                    ->label("Kelas Bacaan Qur'an")
                                    ->content(fn (BoardingBacaanAssessment $record): string => BoardingBacaanAssessment::classLabel($record->kelas_bacaan)),
                                Forms\Components\Placeholder::make('pp')
                                    ->label('Panjang Pendek (PP)')
                                    ->content(fn (BoardingBacaanAssessment $record): string => BoardingBacaanAssessment::gradeLabel($record->pp_grade)),
                                Forms\Components\Placeholder::make('kl')
                                    ->label('Kelancaran (KL)')
                                    ->content(fn (BoardingBacaanAssessment $record): string => BoardingBacaanAssessment::gradeLabel($record->kl_grade)),
                                Forms\Components\Placeholder::make('tj')
                                    ->label('Tajwid (TJ)')
                                    ->content(fn (BoardingBacaanAssessment $record): string => BoardingBacaanAssessment::gradeLabel($record->tj_grade)),
                                Forms\Components\Placeholder::make('mj')
                                    ->label('Mahroj (MJ)')
                                    ->content(fn (BoardingBacaanAssessment $record): string => BoardingBacaanAssessment::gradeLabel($record->mj_grade)),
                                Forms\Components\Placeholder::make('notes')
                                    ->label('Catatan')
                                    ->content(fn (BoardingBacaanAssessment $record): string => $record->notes ?: '-')
                                    ->columnSpanFull(),
                            ]),
                    ]),
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->modalHeading('Edit Penilaian Bacaan')
                    ->modalSubmitActionLabel('Simpan')
                    ->modalWidth('3xl')
                    ->fillForm(fn (BoardingBacaanAssessment $record): array => [
                        'assessed_at' => $record->assessed_at?->toDateString() ?? now()->toDateString(),
                        'pp_grade' => $record->pp_grade,
                        'kl_grade' => $record->kl_grade,
                        'tj_grade' => $record->tj_grade,
                        'mj_grade' => $record->mj_grade,
                        'kelas_bacaan' => $record->kelas_bacaan,
                        'reviewer_mode' => filled($record->reviewer_name) && blank($record->reviewer_user_id) ? 'name' : 'user',
                        'reviewer_user_id' => $record->reviewer_user_id,
                        'reviewer_name' => $record->reviewer_name,
                        'notes' => $record->notes,
                    ])
                    ->form($this->bacaanFormSchema())
                    ->action(function (BoardingBacaanAssessment $record, array $data): void {
                        abort_unless($this->canManageBacaan(), 403);
                        $reviewerPayload = $this->reviewerPayload($data);

                        $record->update([
                            'assessed_at' => $data['assessed_at'],
                            'pp_grade' => $data['pp_grade'],
                            'kl_grade' => $data['kl_grade'],
                            'tj_grade' => $data['tj_grade'],
                            'mj_grade' => $data['mj_grade'],
                            'kelas_bacaan' => $data['kelas_bacaan'],
                            'reviewer_user_id' => $reviewerPayload['reviewer_user_id'],
                            'reviewer_name' => $reviewerPayload['reviewer_name'],
                            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                        ]);

                        $this->forgetSummaryMetrics();

                        Notification::make()
                            ->title('Penilaian bacaan diperbarui.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => $this->canManageBacaan()),
                Action::make('hapus')
                    ->label('Hapus')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Hapus riwayat bacaan?')
                    ->action(function (BoardingBacaanAssessment $record): void {
                        abort_unless($this->canManageBacaan(), 403);

                        $record->delete();

                        $this->forgetSummaryMetrics();

                        Notification::make()
                            ->title('Riwayat bacaan dihapus.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => $this->canManageBacaan()),
            ]);
    }
}
