<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\SurveiResource\Pages;
use App\Filament\Resources\SurveiResource\RelationManagers\TargetsRelationManager;
use App\Models\DataSiswa;
use App\Models\GuruTendik;
use App\Models\Survei;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class SurveiResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = Survei::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?int $navigationSort = 37;

    protected static ?string $navigationLabel = 'Survei';

    protected static ?string $modelLabel = 'survei';

    protected static ?string $pluralModelLabel = 'Survei';

    protected static ?string $permissionPrefix = 'survei';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return SchemaFacade::hasTable('surveis') && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Informasi Survei')
                    ->description('Susun survei, tentukan siapa yang wajib mengisi, lalu sistem akan membuat link unik per target.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Nama Survei')
                            ->required()
                            ->maxLength(180)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('audience_type')
                            ->label('Survei Untuk')
                            ->options(Survei::audienceOptions())
                            ->live()
                            ->required()
                            ->disabled(fn (?Survei $record): bool => $record?->hasSubmissions() ?? false),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktifkan survei')
                            ->default(true),
                        Forms\Components\DatePicker::make('opens_at')
                            ->label('Mulai Dibuka')
                            ->native(true)
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('closes_at')
                            ->label('Ditutup Pada')
                            ->native(true)
                            ->displayFormat('d/m/Y'),
                        Forms\Components\Textarea::make('description')
                            ->label('Keterangan / Instruksi')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Section::make('Target Pengisian')
                    ->description('Pilih target yang wajib mengisi. Setiap target akan menerima link publik token unik yang aman.')
                    ->schema([
                        Forms\Components\Select::make('selected_student_ids')
                            ->label('Daftar Murid Aktif')
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => static::searchStudentTargetOptions($search))
                            ->getOptionLabelsUsing(fn (array $values): array => static::resolveStudentTargetLabels($values))
                            ->required(fn (Get $get): bool => $get('audience_type') === Survei::AUDIENCE_STUDENT)
                            ->hidden(fn (Get $get): bool => $get('audience_type') !== Survei::AUDIENCE_STUDENT)
                            ->disabled(fn (?Survei $record): bool => $record?->hasSubmissions() ?? false)
                            ->helperText('Pilih murid aktif yang akan menerima survei ini. Nomor WA orang tua bisa dilengkapi dari tabel hasil.')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('selected_guru_tendik_ids')
                            ->label('Daftar Guru / Tendik')
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => static::searchTeacherTargetOptions($search))
                            ->getOptionLabelsUsing(fn (array $values): array => static::resolveTeacherTargetLabels($values))
                            ->required(fn (Get $get): bool => $get('audience_type') === Survei::AUDIENCE_TEACHER)
                            ->hidden(fn (Get $get): bool => $get('audience_type') !== Survei::AUDIENCE_TEACHER)
                            ->disabled(fn (?Survei $record): bool => $record?->hasSubmissions() ?? false)
                            ->helperText('Pilih guru dan tendik yang akan menerima survei ini.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Pertanyaan')
                    ->description('Buat pertanyaan survei secara manual. Setelah ada jawaban masuk, pertanyaan dikunci untuk menjaga integritas hasil.')
                    ->schema([
                        Forms\Components\Repeater::make('questions')
                            ->relationship()
                            ->orderColumn('urutan')
                            ->collapsed()
                            ->reorderableWithButtons()
                            ->cloneable()
                            ->defaultItems(1)
                            ->addActionLabel('Tambah Pertanyaan')
                            ->disabled(fn (?Survei $record): bool => $record?->hasSubmissions() ?? false)
                            ->schema([
                                Forms\Components\TextInput::make('prompt')
                                    ->label('Pertanyaan')
                                    ->required()
                                    ->maxLength(500)
                                    ->columnSpanFull(),
                                Forms\Components\Select::make('question_type')
                                    ->label('Jenis Jawaban')
                                    ->options(\App\Models\SurveiQuestion::typeOptions())
                                    ->live()
                                    ->required(),
                                Forms\Components\Toggle::make('is_required')
                                    ->label('Wajib diisi')
                                    ->default(true),
                                Forms\Components\Repeater::make('options')
                                    ->label('Pilihan Jawaban')
                                    ->hidden(fn (Get $get): bool => $get('question_type') !== \App\Models\SurveiQuestion::TYPE_SINGLE_CHOICE)
                                    ->required(fn (Get $get): bool => $get('question_type') === \App\Models\SurveiQuestion::TYPE_SINGLE_CHOICE)
                                    ->defaultItems(2)
                                    ->minItems(2)
                                    ->reorderableWithButtons()
                                    ->addActionLabel('Tambah Pilihan')
                                    ->columnSpanFull()
                                    ->schema([
                                        Forms\Components\TextInput::make('label')
                                            ->label('Pilihan')
                                            ->required()
                                            ->maxLength(120),
                                    ]),
                            ])
                            ->columns(['default' => 1, 'md' => 2]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari nama survei...',
            emptyStateHeading: 'Belum ada survei',
            emptyStateDescription: 'Buat survei baru untuk murid/orang tua atau guru/tendik, lalu pantau pengisiannya dari halaman detail.'
        )
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Survei')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Survei $record): string => collect([
                        Survei::audienceLabel($record->audience_type),
                        'Isi '.$record->submittedTargetsCount().' dari '.$record->totalTargetsCount().' target',
                    ])->implode(' | '))
                    ->wrap(),
                Tables\Columns\TextColumn::make('audience_type')
                    ->label('Untuk')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Survei::audienceLabel($state))
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('completion')
                    ->label('Pengisian')
                    ->badge()
                    ->state(fn (Survei $record): string => $record->completionSummary())
                    ->description(fn (Survei $record): string => 'Belum: '.$record->pendingTargetsCount())
                    ->color(fn (Survei $record): string => $record->submittedTargetsCount() > 0 ? 'success' : 'warning'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('opens_at')
                    ->label('Mulai')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->visibleFrom('lg')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('closes_at')
                    ->label('Tutup')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->visibleFrom('lg')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->since()
                    ->visibleFrom('lg')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('audience_type')
                    ->label('Survei Untuk')
                    ->options(Survei::audienceOptions()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->recordUrl(fn (Survei $record): string => static::getUrl('view', ['record' => $record]))
            ->actions([
                ViewAction::make()
                    ->label('Lihat Data'),
                EditAction::make(),
                static::makeDeleteTableAction('survei')
                    ->visible(fn (Survei $record): bool => ! $record->hasSubmissions()),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount([
                'targets',
                'submittedTargets',
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                Section::make('Ringkasan Survei')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextEntry::make('title')
                            ->label('Nama Survei'),
                        TextEntry::make('audience_type')
                            ->label('Survei Untuk')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => Survei::audienceLabel($state)),
                        IconEntry::make('is_active')
                            ->label('Status Aktif')
                            ->boolean(),
                        TextEntry::make('completion_summary')
                            ->label('Pengisian')
                            ->state(fn (Survei $record): string => $record->completionSummary().' target · Belum '.$record->pendingTargetsCount()),
                        TextEntry::make('opens_at')
                            ->label('Mulai Dibuka')
                            ->date('d/m/Y')
                            ->placeholder('-'),
                        TextEntry::make('closes_at')
                            ->label('Ditutup Pada')
                            ->date('d/m/Y')
                            ->placeholder('-'),
                        TextEntry::make('description')
                            ->label('Keterangan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make('Akses Publik')
                    ->schema([
                        TextEntry::make('public_note')
                            ->label('Catatan Link')
                            ->state('Setiap murid, orang tua, guru, atau tendik menerima link token unik per target. Bagikan link dari tabel pengisian agar aman dan hasil tetap terpetakan.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TargetsRelationManager::class,
        ];
    }

    public static function canDelete($record): bool
    {
        return $record instanceof Survei
            && static::userCanModule('manage')
            && ! $record->hasSubmissions();
    }

    public static function canDeleteAny(): bool
    {
        return static::userCanModule('manage');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurveis::route('/'),
            'create' => Pages\CreateSurvei::route('/create'),
            'view' => Pages\ViewSurvei::route('/{record}'),
            'edit' => Pages\EditSurvei::route('/{record}/edit'),
        ];
    }

    public static function studentTargetOptions(): array
    {
        return DataSiswa::query()
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get(['id', 'nama', 'rombel_saat_ini'])
            ->mapWithKeys(fn (DataSiswa $record): array => [
                $record->getKey() => trim($record->nama.' - '.($record->rombel_saat_ini ?: 'Tanpa rombel')),
            ])
            ->all();
    }

    public static function searchStudentTargetOptions(string $search): array
    {
        return DataSiswa::query()
            ->where('status', 'aktif')
            ->where(function (Builder $query) use ($search): void {
                $query
                    ->where('nama', 'like', '%'.$search.'%')
                    ->orWhere('nisn', 'like', '%'.$search.'%')
                    ->orWhere('rombel_saat_ini', 'like', '%'.$search.'%');
            })
            ->orderBy('nama')
            ->limit(50)
            ->get(['id', 'nama', 'rombel_saat_ini'])
            ->mapWithKeys(fn (DataSiswa $record): array => [
                (string) $record->getKey() => trim($record->nama.' - '.($record->rombel_saat_ini ?: 'Tanpa rombel')),
            ])
            ->all();
    }

    public static function resolveStudentTargetLabels(array $values): array
    {
        $ids = collect($values)
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return DataSiswa::query()
            ->whereIn('id', $ids->all())
            ->get(['id', 'nama', 'rombel_saat_ini'])
            ->mapWithKeys(fn (DataSiswa $record): array => [
                (string) $record->getKey() => trim($record->nama.' - '.($record->rombel_saat_ini ?: 'Tanpa rombel')),
            ])
            ->all();
    }

    public static function teacherTargetOptions(): array
    {
        return GuruTendik::query()
            ->orderBy('nama')
            ->get(['id', 'nama', 'jenis_ptk'])
            ->mapWithKeys(fn (GuruTendik $record): array => [
                $record->getKey() => trim($record->nama.' - '.($record->jenis_ptk ?: 'Guru/Tendik')),
            ])
            ->all();
    }

    public static function searchTeacherTargetOptions(string $search): array
    {
        return GuruTendik::query()
            ->where(function (Builder $query) use ($search): void {
                $query
                    ->where('nama', 'like', '%'.$search.'%')
                    ->orWhere('jenis_ptk', 'like', '%'.$search.'%')
                    ->orWhere('nip', 'like', '%'.$search.'%');
            })
            ->orderBy('nama')
            ->limit(50)
            ->get(['id', 'nama', 'jenis_ptk'])
            ->mapWithKeys(fn (GuruTendik $record): array => [
                (string) $record->getKey() => trim($record->nama.' - '.($record->jenis_ptk ?: 'Guru/Tendik')),
            ])
            ->all();
    }

    public static function resolveTeacherTargetLabels(array $values): array
    {
        $ids = collect($values)
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return GuruTendik::query()
            ->whereIn('id', $ids->all())
            ->get(['id', 'nama', 'jenis_ptk'])
            ->mapWithKeys(fn (GuruTendik $record): array => [
                (string) $record->getKey() => trim($record->nama.' - '.($record->jenis_ptk ?: 'Guru/Tendik')),
            ])
            ->all();
    }

    public static function initialTargetFormData(Survei $survei): array
    {
        return [
            'selected_student_ids' => $survei->targets()
                ->whereNotNull('data_siswa_id')
                ->pluck('data_siswa_id')
                ->map(fn (mixed $id): string => (string) $id)
                ->all(),
            'selected_guru_tendik_ids' => $survei->targets()
                ->whereNotNull('guru_tendik_id')
                ->pluck('guru_tendik_id')
                ->map(fn (mixed $id): string => (string) $id)
                ->all(),
        ];
    }

    public static function syncTargets(Survei $survei, array $data): void
    {
        if ($survei->hasSubmissions()) {
            return;
        }

        $audienceType = (string) ($data['audience_type'] ?? $survei->audience_type);
        $selectedIds = collect(
            $audienceType === Survei::AUDIENCE_STUDENT
                ? ($data['selected_student_ids'] ?? [])
                : ($data['selected_guru_tendik_ids'] ?? [])
        )
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $existingTargets = $survei->targets()->get();
        $keepTargetIds = [];

        if ($audienceType === Survei::AUDIENCE_STUDENT) {
            $hasStudentWhatsappColumn = SchemaFacade::hasColumn('data_siswa', 'wa_ortu');

            $students = DataSiswa::query()
                ->where('status', 'aktif')
                ->whereIn('id', $selectedIds)
                ->get(['id', 'nama', 'rombel_saat_ini', ...($hasStudentWhatsappColumn ? ['wa_ortu'] : [])]);

            foreach ($students as $student) {
                $target = $existingTargets
                    ->first(fn ($item): bool => (int) $item->data_siswa_id === (int) $student->getKey());

                $attributes = [
                    'audience_type' => Survei::AUDIENCE_STUDENT,
                    'data_siswa_id' => $student->getKey(),
                    'guru_tendik_id' => null,
                    'recipient_name_snapshot' => $student->nama,
                    'recipient_context_snapshot' => $student->rombel_saat_ini,
                ];

                if ($target) {
                    $target->fill($attributes);

                    if (blank($target->whatsapp_number) && $hasStudentWhatsappColumn) {
                        $target->whatsapp_number = trim((string) $student->getAttribute('wa_ortu'));
                    }

                    $target->save();
                    $keepTargetIds[] = $target->getKey();
                    continue;
                }

                $created = $survei->targets()->create(array_merge($attributes, [
                    'whatsapp_number' => $hasStudentWhatsappColumn ? trim((string) $student->getAttribute('wa_ortu')) : null,
                ]));

                $keepTargetIds[] = $created->getKey();
            }
        }

        if ($audienceType === Survei::AUDIENCE_TEACHER) {
            $teachers = GuruTendik::query()
                ->whereIn('id', $selectedIds)
                ->get(['id', 'nama', 'jenis_ptk']);

            foreach ($teachers as $teacher) {
                $target = $existingTargets
                    ->first(fn ($item): bool => (int) $item->guru_tendik_id === (int) $teacher->getKey());

                $attributes = [
                    'audience_type' => Survei::AUDIENCE_TEACHER,
                    'data_siswa_id' => null,
                    'guru_tendik_id' => $teacher->getKey(),
                    'recipient_name_snapshot' => $teacher->nama,
                    'recipient_context_snapshot' => $teacher->jenis_ptk,
                ];

                if ($target) {
                    $target->fill($attributes);
                    $target->save();
                    $keepTargetIds[] = $target->getKey();
                    continue;
                }

                $created = $survei->targets()->create($attributes);
                $keepTargetIds[] = $created->getKey();
            }
        }

        $survei->targets()
            ->when($keepTargetIds !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $keepTargetIds))
            ->when($keepTargetIds === [], fn (Builder $query): Builder => $query)
            ->delete();
    }
}
