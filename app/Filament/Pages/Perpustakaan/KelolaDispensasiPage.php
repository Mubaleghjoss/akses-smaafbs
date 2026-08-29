<?php

namespace App\Filament\Pages\Perpustakaan;

use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use App\Models\DataSiswa;
use App\Models\PerpustakaanLiterasiDispensation;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\User;
use App\Support\Perpustakaan\LiteracyDispensationWriter;
use App\Support\Perpustakaan\LiteracyRespondentBase;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Validation\ValidationException;

/**
 * Kelola dispensasi literasi dari satu tempat.
 *
 * Sebelumnya dispensasi hanya bisa ditetapkan satu-satu dari panel di halaman
 * daftar soal, sehingga satu siswa yang tes MT harus ditandai berulang kali di
 * tiap materi. Halaman ini menambahkan tabel terpusat plus aksi massal lintas
 * materi, dan alasan/keterangan bisa diubah tanpa hapus-buat ulang.
 */
class KelolaDispensasiPage extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-minus';

    protected static string|\UnitEnum|null $navigationGroup = 'Perpustakaan';

    protected static ?string $navigationLabel = 'Kelola Dispensasi';

    protected static ?string $slug = 'kelola-dispensasi';

    protected static ?string $title = 'Kelola Dispensasi Literasi';

    protected static ?int $navigationSort = 22;

    protected static ?string $permissionPrefix = 'perpustakaan_literasi';

    protected string $view = 'filament.pages.perpustakaan.kelola-dispensasi';

    public static function shouldRegisterNavigation(): bool
    {
        return static::hasRequiredTables() && static::userCanModule('view');
    }

    public static function canAccess(): bool
    {
        return static::hasRequiredTables() && static::userCanModule('view');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => PerpustakaanLiterasiDispensation::query()
                ->with(['material:id,title,program_category', 'confirmedBy:id,name']))
            ->defaultSort('confirmed_at', 'desc')
            ->striped()
            ->deferLoading()
            ->defaultPaginationPageOption(25)
            ->paginated([10, 25, 50, 100])
            ->persistFiltersInSession()
            ->searchPlaceholder('Cari nama siswa atau kelas...')
            ->emptyStateHeading('Belum ada dispensasi')
            ->emptyStateDescription('Tetapkan dispensasi lewat tombol "Tetapkan Dispensasi" di atas.')
            ->columns([
                Tables\Columns\TextColumn::make('student_name_snapshot')
                    ->label('Siswa')
                    ->description(fn (PerpustakaanLiterasiDispensation $record): string => $record->student_class_snapshot ?: 'Tanpa kelas')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('student_class_snapshot')
                    ->label('Kelas')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('material.title')
                    ->label('Materi')
                    ->state(fn (PerpustakaanLiterasiDispensation $record): string => $record->material?->title ?: 'Materi tidak ditemukan')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Alasan')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => PerpustakaanLiterasiDispensation::reasonOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        PerpustakaanLiterasiDispensation::REASON_PERMISSION => 'info',
                        PerpustakaanLiterasiDispensation::REASON_SICK => 'warning',
                        PerpustakaanLiterasiDispensation::REASON_MT_TEST => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('note')
                    ->label('Keterangan')
                    ->placeholder('-')
                    ->limit(60)
                    ->tooltip(fn (PerpustakaanLiterasiDispensation $record): ?string => $record->note)
                    ->wrap(),
                Tables\Columns\TextColumn::make('confirmed_at')
                    ->label('Ditetapkan')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (PerpustakaanLiterasiDispensation $record): string => $record->confirmedBy?->name ?: '-')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('reason')
                    ->label('Alasan')
                    ->options(fn (): array => PerpustakaanLiterasiDispensation::reasonOptions()),
                Tables\Filters\SelectFilter::make('student_class_snapshot')
                    ->label('Kelas')
                    ->options(fn (): array => collect(LiteracyRespondentBase::activeClassNames())
                        ->mapWithKeys(fn (string $class): array => [$class => $class])
                        ->all()),
                Tables\Filters\SelectFilter::make('material_id')
                    ->label('Materi')
                    ->searchable()
                    ->options(fn (): array => PerpustakaanLiterasiMaterial::query()
                        ->orderByDesc('opens_at')
                        ->limit(200)
                        ->pluck('title', 'id')
                        ->all()),
            ])
            ->actions([
                Action::make('ubah')
                    ->label('Ubah')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => static::userCanModule('manage'))
                    ->modalHeading('Ubah alasan dispensasi')
                    ->modalSubmitActionLabel('Simpan')
                    ->fillForm(fn (PerpustakaanLiterasiDispensation $record): array => [
                        'reason' => $record->reason,
                        'note' => $record->note,
                    ])
                    ->schema([
                        Forms\Components\Select::make('reason')
                            ->label('Alasan')
                            ->options(PerpustakaanLiterasiDispensation::reasonOptions())
                            ->required()
                            ->live()
                            ->native(false),
                        Forms\Components\Textarea::make('note')
                            ->label('Keterangan')
                            ->rows(3)
                            ->maxLength(1000)
                            ->helperText('Wajib diisi minimal 5 karakter untuk alasan Izin.')
                            ->required(fn (Get $get): bool => $get('reason') === PerpustakaanLiterasiDispensation::REASON_PERMISSION),
                    ])
                    ->action(function (PerpustakaanLiterasiDispensation $record, array $data): void {
                        try {
                            LiteracyDispensationWriter::update(
                                $record,
                                $data['reason'],
                                $data['note'] ?? null,
                                auth()->user(),
                            );
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Gagal menyimpan')
                                ->body(collect($exception->errors())->flatten()->implode(' '))
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Dispensasi diperbarui')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->label('Batalkan')
                    ->modalHeading('Batalkan dispensasi?')
                    ->modalDescription('Siswa akan kembali dihitung ke dalam basis responden pada materi ini.')
                    ->visible(fn (): bool => static::userCanModule('manage')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Batalkan Terpilih')
                        ->modalDescription('Siswa terpilih akan kembali dihitung ke dalam basis responden.')
                        ->visible(fn (): bool => static::userCanModule('manage')),
                ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('tetapkanMassal')
                ->label('Tetapkan Dispensasi')
                ->icon('heroicon-o-plus-circle')
                ->visible(fn (): bool => static::userCanModule('manage'))
                ->modalHeading('Tetapkan dispensasi lintas materi')
                ->modalDescription('Pilih siswa dan materi. Satu alasan diterapkan ke semua kombinasi yang dipilih. Siswa yang sudah mengisi akan dilewati dan dilaporkan.')
                ->modalSubmitActionLabel('Terapkan')
                ->schema([
                    Forms\Components\Select::make('student_ids')
                        ->label('Siswa')
                        ->multiple()
                        ->required()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => DataSiswa::query()
                            ->where('status', 'aktif')
                            ->orderBy('rombel_saat_ini')
                            ->orderBy('nama')
                            ->get(['id', 'nama', 'rombel_saat_ini'])
                            ->mapWithKeys(fn (DataSiswa $student): array => [
                                $student->getKey() => $student->nama.' — '.($student->rombel_saat_ini ?: 'Tanpa kelas'),
                            ])
                            ->all()),
                    Forms\Components\Select::make('material_ids')
                        ->label('Materi')
                        ->multiple()
                        ->required()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => PerpustakaanLiterasiMaterial::query()
                            ->orderByDesc('opens_at')
                            ->limit(200)
                            ->get(['id', 'title', 'program_category'])
                            ->mapWithKeys(fn (PerpustakaanLiterasiMaterial $material): array => [
                                $material->getKey() => $material->title.' — '.PerpustakaanLiterasiMaterial::programCategoryLabel($material->program_category),
                            ])
                            ->all()),
                    Forms\Components\Select::make('reason')
                        ->label('Alasan')
                        ->options(PerpustakaanLiterasiDispensation::reasonOptions())
                        ->required()
                        ->live()
                        ->native(false),
                    Forms\Components\Textarea::make('note')
                        ->label('Keterangan')
                        ->rows(3)
                        ->maxLength(1000)
                        ->helperText('Wajib diisi minimal 5 karakter untuk alasan Izin.')
                        ->required(fn (Get $get): bool => $get('reason') === PerpustakaanLiterasiDispensation::REASON_PERMISSION),
                ])
                ->action(function (array $data): void {
                    try {
                        $result = LiteracyDispensationWriter::assignBulk(
                            array_map('intval', $data['student_ids'] ?? []),
                            array_map('intval', $data['material_ids'] ?? []),
                            $data['reason'],
                            $data['note'] ?? null,
                            auth()->user(),
                        );
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Gagal menerapkan')
                            ->body(collect($exception->errors())->flatten()->implode(' '))
                            ->danger()
                            ->send();

                        return;
                    }

                    $skipped = $result['skipped'];

                    Notification::make()
                        ->title($result['applied'].' dispensasi diterapkan')
                        ->body($skipped === []
                            ? 'Semua kombinasi berhasil.'
                            : count($skipped).' dilewati: '.implode(' | ', array_slice($skipped, 0, 5))
                                .(count($skipped) > 5 ? ' …' : ''))
                        ->status($result['applied'] > 0 ? 'success' : 'warning')
                        ->persistent()
                        ->send();
                }),
            Action::make('bukaAnalisis')
                ->label('Buka Analisis')
                ->icon('heroicon-o-chart-bar-square')
                ->color('gray')
                ->url(fn (): string => AnalisisLiterasiPage::getUrl())
                ->visible(fn (): bool => AnalisisLiterasiPage::canAccess()),
            Action::make('kelolaSoal')
                ->label('Kelola Soal')
                ->icon('heroicon-o-queue-list')
                ->color('gray')
                ->url(fn (): string => PerpustakaanLiterasiMaterialResource::getUrl())
                ->visible(fn (): bool => PerpustakaanLiterasiMaterialResource::canViewAny()),
        ];
    }

    protected static function hasRequiredTables(): bool
    {
        return SchemaFacade::hasTable('perpustakaan_literasi_dispensations')
            && SchemaFacade::hasTable('perpustakaan_literasi_materials')
            && SchemaFacade::hasTable('data_siswa');
    }

    protected static function userCanModule(string $ability): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $user->loadMissing('roles');

        if ($user->hasFullAdminAccess()) {
            return true;
        }

        $prefix = static::$permissionPrefix;

        if (blank($prefix)) {
            return false;
        }

        return $ability === 'view'
            ? $user->canViewModule($prefix)
            : $user->canManageModule($prefix);
    }
}
