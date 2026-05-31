<?php

namespace App\Filament\Resources\BoardingPencapaianResource\Pages;

use App\Filament\Resources\BoardingPencapaianResource;
use App\Models\BoardingMaknaProgress;
use App\Models\BoardingPencapaian;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ManageMakna extends Page implements HasTable
{
    use InteractsWithRecord;
    use Tables\Concerns\InteractsWithTable;

    protected ?array $summaryMetricsCache = null;

    protected static string $resource = BoardingPencapaianResource::class;

    protected static ?string $title = 'Makna per Murid';

    protected static ?string $breadcrumb = 'Makna';

    protected string $view = 'filament.resources.boarding-pencapaian-resource.pages.manage-makna';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->getRecord()->loadMissing('siswa:id,nama,rombel_saat_ini');

        abort_unless(static::getResource()::canAccess(), 403);

        BoardingMaknaProgress::ensureDefaultsForPencapaian($this->getRecord());
    }

    public function getRecord(): BoardingPencapaian
    {
        /** @var BoardingPencapaian $record */
        $record = $this->record;

        return $record;
    }

    protected function canManageMakna(): bool
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
            $counts = BoardingMaknaProgress::query()
                ->where('boarding_pencapaian_id', $this->getRecord()->getKey())
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $total = BoardingMaknaProgress::defaultTargetCount();
            $khatam = (int) ($counts['khatam'] ?? 0);
            $partial = (int) ($counts['sebagian'] ?? 0);

            $this->summaryMetricsCache = [
                'total_targets' => $total,
                'khatam' => $khatam,
                'partial' => $partial,
                'blank' => max($total - $khatam - $partial, 0),
            ];
        }

        return [
            'summaryMetrics' => $this->summaryMetricsCache,
        ];
    }

    protected function statusColor(string $status): string
    {
        return match ($status) {
            'khatam' => 'success',
            'sebagian' => 'warning',
            default => 'gray',
        };
    }

    protected function maknaGroupOrderSql(string $column = 'target_group'): string
    {
        $cases = [];

        foreach (array_keys(BoardingMaknaProgress::GROUP_OPTIONS) as $index => $group) {
            $cases[] = "WHEN {$column} = '".str_replace("'", "''", $group)."' THEN {$index}";
        }

        return 'CASE '.implode(' ', $cases).' ELSE 999 END';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => BoardingMaknaProgress::query()
                ->where('boarding_pencapaian_id', $this->getRecord()->getKey())
                ->select([
                    'id',
                    'boarding_pencapaian_id',
                    'target_group',
                    'target_name',
                    'status',
                    'remaining_pages',
                    'total_pages',
                    'updated_by_user_id',
                    'updated_at',
                    'urutan',
                ])
                ->with('updatedByUser:id,name'))
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw($this->maknaGroupOrderSql())
                ->orderBy('target_group')
                ->orderBy('urutan')
                ->orderBy('id'))
            ->persistFiltersInSession()
            ->searchPlaceholder('Cari target makna...')
            ->emptyStateHeading('Belum ada target makna')
            ->emptyStateDescription('Target makna akan otomatis muncul.')
            ->defaultPaginationPageOption(200)
            ->paginated([25, 50, 100, 200])
            ->filters([
                Tables\Filters\SelectFilter::make('target_group')
                    ->label('Bagian')
                    ->options(BoardingMaknaProgress::groupOptions()),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(BoardingMaknaProgress::statusOptions()),
            ])
            ->groups([
                Group::make('target_group')
                    ->label('Bagian')
                    ->collapsible()
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query
                        ->orderByRaw($this->maknaGroupOrderSql()." {$direction}")
                        ->orderBy('target_group', $direction))
                    ->getTitleFromRecordUsing(fn (BoardingMaknaProgress $record): string => BoardingMaknaProgress::groupLabel($record->target_group)),
            ])
            ->defaultGroup('target_group')
            ->collapsedGroupsByDefault()
            ->columns([
                Tables\Columns\TextColumn::make('target_group')
                    ->label('Bagian')
                    ->badge()
                    ->state(fn (BoardingMaknaProgress $record): string => BoardingMaknaProgress::groupLabel($record->target_group))
                    ->color(fn (BoardingMaknaProgress $record): string => $record->target_group === 'quran' ? 'success' : 'warning')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('target_name')
                    ->label('Materi')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\SelectColumn::make('status')
                    ->label('Status')
                    ->options(BoardingMaknaProgress::statusOptions())
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->rules(['required', 'string', 'max:30'])
                    ->disabled(fn (): bool => ! $this->canManageMakna()),
                Tables\Columns\TextInputColumn::make('remaining_pages')
                    ->label('Kurang')
                    ->type('number')
                    ->placeholder('-')
                    ->rules(['nullable', 'integer', 'min:0'])
                    ->extraInputAttributes(['min' => 0, 'inputmode' => 'numeric'])
                    ->disabled(fn (BoardingMaknaProgress $record): bool => $record->status !== 'sebagian' || ! $this->canManageMakna())
                    ->alignCenter(),
                Tables\Columns\TextInputColumn::make('total_pages')
                    ->label('Dari')
                    ->type('number')
                    ->placeholder('-')
                    ->rules(['nullable', 'integer', 'min:0'])
                    ->extraInputAttributes(['min' => 0, 'inputmode' => 'numeric'])
                    ->disabled(fn (BoardingMaknaProgress $record): bool => $record->status !== 'sebagian' || ! $this->canManageMakna())
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('updated_by')
                    ->label('Diupdate')
                    ->state(fn (BoardingMaknaProgress $record): string => collect([
                        $record->updatedByUser?->name ?: null,
                        $record->updated_at ? Carbon::parse($record->updated_at)->translatedFormat('d M Y H:i') : null,
                    ])->filter()->implode(' | ') ?: '-')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visibleFrom('lg'),
            ])
            ->actions([
                Action::make('ubah')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->modalHeading(fn (BoardingMaknaProgress $record): string => 'Ubah target makna: '.$record->target_name)
                    ->modalSubmitActionLabel('Simpan')
                    ->modalWidth('lg')
                    ->fillForm(fn (BoardingMaknaProgress $record): array => [
                        'status' => $record->status,
                        'remaining_pages' => $record->remaining_pages,
                        'total_pages' => $record->total_pages,
                    ])
                    ->form([
                        Forms\Components\Radio::make('status')
                            ->label('Status')
                            ->options(BoardingMaknaProgress::statusOptions())
                            ->default('belum_diisi')
                            ->live()
                            ->required(),
                        Forms\Components\TextInput::make('remaining_pages')
                            ->label('Kurang Berapa Lembar')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Isi jika status masih sebagian.')
                            ->visible(fn (Get $get): bool => $get('status') === 'sebagian')
                            ->required(fn (Get $get): bool => $get('status') === 'sebagian'),
                        Forms\Components\TextInput::make('total_pages')
                            ->label('Dari Berapa Lembar')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Total lembar target, diisi manual saat status sebagian.')
                            ->visible(fn (Get $get): bool => $get('status') === 'sebagian')
                            ->required(fn (Get $get): bool => $get('status') === 'sebagian'),
                    ])
                    ->action(function (BoardingMaknaProgress $record, array $data): void {
                        abort_unless($this->canManageMakna(), 403);

                        $record->update([
                            'status' => $data['status'] ?? 'belum_diisi',
                            'remaining_pages' => ($data['status'] ?? 'belum_diisi') === 'sebagian'
                                ? (filled($data['remaining_pages'] ?? null) ? (int) $data['remaining_pages'] : null)
                                : null,
                            'total_pages' => ($data['status'] ?? 'belum_diisi') === 'sebagian'
                                ? (filled($data['total_pages'] ?? null) ? (int) $data['total_pages'] : null)
                                : null,
                        ]);

                        $this->forgetSummaryMetrics();

                        Notification::make()
                            ->title('Progress makna diperbarui.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => $this->canManageMakna()),
            ]);
    }
}
