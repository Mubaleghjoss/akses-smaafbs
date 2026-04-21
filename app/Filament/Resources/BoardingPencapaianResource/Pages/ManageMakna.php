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
                    'updated_by_user_id',
                    'updated_at',
                    'urutan',
                ])
                ->with('updatedByUser:id,name'))
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderBy('target_group')
                ->orderBy('urutan')
                ->orderBy('id'))
            ->persistFiltersInSession()
            ->searchPlaceholder('Cari target makna...')
            ->emptyStateHeading('Belum ada target makna')
            ->emptyStateDescription('Target makna akan otomatis muncul untuk murid ini.')
            ->groups([
                Group::make('target_group')
                    ->label('Kelompok')
                    ->getTitleFromRecordUsing(fn (BoardingMaknaProgress $record): string => BoardingMaknaProgress::groupLabel($record->target_group)),
            ])
            ->defaultGroup('target_group')
            ->filters([
                Tables\Filters\SelectFilter::make('target_group')
                    ->label('Kelompok')
                    ->options(BoardingMaknaProgress::groupOptions()),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(BoardingMaknaProgress::statusOptions()),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('target_name')
                    ->label('Target')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (BoardingMaknaProgress $record): string => BoardingMaknaProgress::statusLabel($record->status))
                    ->color(fn (BoardingMaknaProgress $record): string => $this->statusColor($record->status))
                    ->wrap(),
                Tables\Columns\TextColumn::make('remaining_pages')
                    ->label('Kurang')
                    ->state(fn (BoardingMaknaProgress $record): string => $record->status === 'sebagian' && filled($record->remaining_pages)
                        ? ((int) $record->remaining_pages).' lembar'
                        : '-')
                    ->description(fn (BoardingMaknaProgress $record): ?string => $record->updated_at
                        ? 'Update '.Carbon::parse($record->updated_at)->translatedFormat('d M Y H:i')
                        : null)
                    ->wrap(),
                Tables\Columns\TextColumn::make('updated_by')
                    ->label('Diupdate')
                    ->state(fn (BoardingMaknaProgress $record): string => $record->updatedByUser?->name ?: '-')
                    ->toggleable()
                    ->visibleFrom('md'),
            ])
            ->actions([
                Action::make('ubah')
                    ->label('Ubah')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->modalHeading(fn (BoardingMaknaProgress $record): string => 'Ubah target makna: '.$record->target_name)
                    ->modalSubmitActionLabel('Simpan')
                    ->modalWidth('lg')
                    ->fillForm(fn (BoardingMaknaProgress $record): array => [
                        'status' => $record->status,
                        'remaining_pages' => $record->remaining_pages,
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
                            ->helperText('Opsional. Isi hanya jika status masih sebagian.')
                            ->visible(fn (Get $get): bool => $get('status') === 'sebagian'),
                    ])
                    ->action(function (BoardingMaknaProgress $record, array $data): void {
                        abort_unless($this->canManageMakna(), 403);

                        $record->update([
                            'status' => $data['status'] ?? 'belum_diisi',
                            'remaining_pages' => ($data['status'] ?? 'belum_diisi') === 'sebagian'
                                ? (filled($data['remaining_pages'] ?? null) ? (int) $data['remaining_pages'] : null)
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
