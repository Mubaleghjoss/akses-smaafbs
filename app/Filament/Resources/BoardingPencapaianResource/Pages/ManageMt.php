<?php

namespace App\Filament\Resources\BoardingPencapaianResource\Pages;

use App\Filament\Resources\BoardingPencapaianResource;
use App\Models\BoardingMtProgress;
use App\Models\BoardingPencapaian;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ManageMt extends Page implements HasTable
{
    use InteractsWithRecord;
    use Tables\Concerns\InteractsWithTable;

    protected static string $resource = BoardingPencapaianResource::class;

    protected static ?string $title = 'Materi MT per Murid';

    protected static ?string $breadcrumb = 'Materi MT';

    protected string $view = 'filament.resources.boarding-pencapaian-resource.pages.manage-mt';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->getRecord()->loadMissing('siswa:id,nama,rombel_saat_ini');

        abort_unless(static::getResource()::canAccess(), 403);

        BoardingMtProgress::ensureDefaultsForPencapaian($this->getRecord());
    }

    public function getRecord(): BoardingPencapaian
    {
        /** @var BoardingPencapaian $record */
        $record = $this->record;

        return $record;
    }

    protected function canManageMt(): bool
    {
        return static::getResource()::canEdit($this->getRecord());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => BoardingMtProgress::query()
                ->where('boarding_pencapaian_id', $this->getRecord()->getKey())
                ->with('updatedByUser:id,name'))
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw("CASE target_group WHEN 'makna_hadits' THEN 10 WHEN 'materi_tambahan' THEN 20 WHEN 'hafalan' THEN 30 WHEN 'catatan_saran' THEN 40 ELSE 99 END")
                ->orderBy('urutan')
                ->orderBy('id'))
            ->paginated(false)
            ->persistFiltersInSession()
            ->searchPlaceholder('Cari materi MT...')
            ->emptyStateHeading('Belum ada materi MT')
            ->emptyStateDescription('Target materi MT akan otomatis muncul untuk murid ini.')
            ->filters([
                Tables\Filters\SelectFilter::make('target_group')
                    ->label('Bagian')
                    ->native(false)
                    ->options(BoardingMtProgress::groupOptions()),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('target_group')
                    ->label('Bagian')
                    ->badge()
                    ->state(fn (BoardingMtProgress $record): string => BoardingMtProgress::groupLabel($record->target_group))
                    ->color(fn (BoardingMtProgress $record): string => match ($record->target_group) {
                        'makna_hadits' => 'warning',
                        'materi_tambahan' => 'success',
                        'hafalan' => 'info',
                        'catatan_saran' => 'gray',
                        default => 'gray',
                    })
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('target_name')
                    ->label('Materi')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextInputColumn::make('progress_value')
                    ->label('Sudah')
                    ->type('number')
                    ->placeholder('-')
                    ->rules(['nullable', 'integer', 'min:0'])
                    ->extraInputAttributes(['min' => 0, 'inputmode' => 'numeric'])
                    ->disabled(fn (BoardingMtProgress $record): bool => $record->input_type !== 'progress' || ! $this->canManageMt())
                    ->alignCenter(),
                Tables\Columns\TextInputColumn::make('target_total')
                    ->label('Target')
                    ->type('number')
                    ->placeholder('-')
                    ->rules(['nullable', 'integer', 'min:0'])
                    ->extraInputAttributes(['min' => 0, 'inputmode' => 'numeric'])
                    ->disabled(fn (BoardingMtProgress $record): bool => $record->input_type !== 'progress' || ! $this->canManageMt())
                    ->alignCenter(),
                Tables\Columns\SelectColumn::make('grade')
                    ->label('Nilai')
                    ->options(fn (BoardingMtProgress $record): array => $record->gradeOptions())
                    ->native(false)
                    ->rules(['nullable', 'string', 'max:30'])
                    ->disabled(fn (BoardingMtProgress $record): bool => $record->input_type !== 'grade' || ! $this->canManageMt()),
                Tables\Columns\TextInputColumn::make('notes')
                    ->label('Catatan')
                    ->placeholder('-')
                    ->rules(['nullable', 'string', 'max:65535'])
                    ->disabled(fn (): bool => ! $this->canManageMt()),
                Tables\Columns\TextColumn::make('updated_by')
                    ->label('Diupdate')
                    ->state(fn (BoardingMtProgress $record): string => collect([
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
                    ->modalHeading(fn (BoardingMtProgress $record): string => 'Ubah Materi MT: '.$record->target_name)
                    ->modalSubmitActionLabel('Simpan')
                    ->modalWidth('lg')
                    ->fillForm(fn (BoardingMtProgress $record): array => [
                        'progress_value' => $record->progress_value,
                        'target_total' => $record->target_total,
                        'grade' => $record->grade,
                        'notes' => $record->notes,
                    ])
                    ->form(fn (BoardingMtProgress $record): array => $record->input_type === 'progress'
                        ? [
                            Forms\Components\TextInput::make('progress_value')
                                ->label('Sudah Berapa '.$record->unit_label)
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->required(),
                            Forms\Components\TextInput::make('target_total')
                                ->label('Dari Berapa '.$record->unit_label)
                                ->numeric()
                                ->minValue(0)
                                ->required(),
                            Forms\Components\Textarea::make('notes')
                                ->label('Catatan / Saran')
                                ->rows(3),
                        ]
                        : [
                            Forms\Components\Radio::make('grade')
                                ->label('Nilai')
                                ->options($record->gradeOptions())
                                ->required(),
                            Forms\Components\Textarea::make('notes')
                                ->label('Catatan / Saran')
                                ->rows(3),
                        ])
                    ->action(function (BoardingMtProgress $record, array $data): void {
                        abort_unless($this->canManageMt(), 403);

                        $record->update([
                            'progress_value' => filled($data['progress_value'] ?? null) ? (int) $data['progress_value'] : null,
                            'target_total' => filled($data['target_total'] ?? null) ? (int) $data['target_total'] : null,
                            'grade' => $data['grade'] ?? null,
                            'notes' => $data['notes'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Progress Materi MT diperbarui.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => $this->canManageMt()),
            ]);
    }
}
