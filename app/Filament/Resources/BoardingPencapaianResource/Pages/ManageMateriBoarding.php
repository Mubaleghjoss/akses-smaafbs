<?php

namespace App\Filament\Resources\BoardingPencapaianResource\Pages;

use App\Filament\Resources\BoardingPencapaianResource;
use App\Models\BoardingMaknaProgress;
use App\Models\BoardingMateriProgress;
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

class ManageMateriBoarding extends Page implements HasTable
{
    use InteractsWithRecord;
    use Tables\Concerns\InteractsWithTable;

    protected static string $resource = BoardingPencapaianResource::class;

    protected static ?string $title = 'Materi Boarding per Murid';

    protected static ?string $breadcrumb = 'Materi Boarding';

    protected string $view = 'filament.resources.boarding-pencapaian-resource.pages.manage-materi-boarding';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->getRecord()->loadMissing('siswa:id,nama,rombel_saat_ini');

        abort_unless(static::getResource()::canAccess(), 403);

        BoardingMaknaProgress::ensureDefaultsForPencapaian($this->getRecord());
        BoardingMateriProgress::ensureDefaultsForPencapaian($this->getRecord());
    }

    public function getRecord(): BoardingPencapaian
    {
        /** @var BoardingPencapaian $record */
        $record = $this->record;

        return $record;
    }

    protected function getViewData(): array
    {
        return [
            'hafalanUrl' => BoardingPencapaianResource::getUrl('hafalan', ['record' => $this->getRecord()]),
            'maknaUrl' => BoardingPencapaianResource::getUrl('makna', ['record' => $this->getRecord()]),
            'bacaanUrl' => BoardingPencapaianResource::getUrl('bacaan', ['record' => $this->getRecord()]),
        ];
    }

    protected function canManageMateri(): bool
    {
        return static::getResource()::canEdit($this->getRecord());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => BoardingMateriProgress::query()
                ->where('boarding_pencapaian_id', $this->getRecord()->getKey())
                ->with('updatedByUser:id,name'))
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw("CASE target_group WHEN 'pengetesan_makna' THEN 10 WHEN 'catatan_saran' THEN 20 ELSE 99 END")
                ->orderBy('urutan')
                ->orderBy('id'))
            ->paginated(false)
            ->persistFiltersInSession()
            ->searchPlaceholder('Cari pengetesan, catatan, atau saran...')
            ->emptyStateHeading('Belum ada materi boarding')
            ->emptyStateDescription('Target pengetesan dan catatan akan otomatis muncul.')
            ->filters([
                Tables\Filters\SelectFilter::make('target_group')
                    ->label('Materi :')
                    ->native(false)
                    ->options(BoardingMateriProgress::groupOptions()),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('target_group')
                    ->label('Bagian')
                    ->badge()
                    ->state(fn (BoardingMateriProgress $record): string => BoardingMateriProgress::groupLabel($record->target_group))
                    ->color(fn (BoardingMateriProgress $record): string => $record->target_group === 'catatan_saran' ? 'info' : 'warning')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('target_name')
                    ->label('Materi')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\SelectColumn::make('grade')
                    ->label('Nilai')
                    ->options(BoardingMateriProgress::gradeOptions())
                    ->native(false)
                    ->rules(['nullable', 'string', 'max:30'])
                    ->disabled(fn (): bool => ! $this->canManageMateri()),
                Tables\Columns\TextInputColumn::make('notes')
                    ->label('Catatan')
                    ->placeholder('-')
                    ->rules(['nullable', 'string', 'max:65535'])
                    ->disabled(fn (): bool => ! $this->canManageMateri()),
                Tables\Columns\TextColumn::make('updated_by')
                    ->label('Diupdate')
                    ->state(fn (BoardingMateriProgress $record): string => collect([
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
                    ->modalHeading(fn (BoardingMateriProgress $record): string => 'Ubah Materi Boarding: '.$record->target_name)
                    ->modalSubmitActionLabel('Simpan')
                    ->modalWidth('lg')
                    ->fillForm(fn (BoardingMateriProgress $record): array => [
                        'grade' => $record->grade,
                        'notes' => $record->notes,
                    ])
                    ->form([
                        Forms\Components\Radio::make('grade')
                            ->label('Nilai')
                            ->options(BoardingMateriProgress::gradeOptions())
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan / Saran')
                            ->rows(3),
                    ])
                    ->action(function (BoardingMateriProgress $record, array $data): void {
                        abort_unless($this->canManageMateri(), 403);

                        $record->update([
                            'grade' => $data['grade'] ?? null,
                            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                        ]);

                        Notification::make()
                            ->title('Materi Boarding diperbarui.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => $this->canManageMateri()),
            ]);
    }
}
