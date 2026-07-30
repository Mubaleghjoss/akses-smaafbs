<?php

namespace App\Filament\Resources;

use App\Actions\Assessment\CloseAssessmentEntryAction;
use App\Actions\Assessment\CreateAssessmentPeriodSnapshotAction;
use App\Actions\Assessment\LockAssessmentPeriodAction;
use App\Actions\Assessment\PublishAssessmentPeriodAction;
use App\Actions\Assessment\ReopenAssessmentPeriodAction;
use App\Actions\Assessment\StartAssessmentVerificationAction;
use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Filament\Concerns\HasAssessmentPermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\AssessmentPeriodResource\Pages;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\Semester;
use App\Models\Rombel;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class AssessmentPeriodResource extends Resource
{
    use HasAssessmentPermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = AssessmentPeriod::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Penilaian';

    protected static ?string $navigationLabel = 'Periode Penilaian';

    protected static ?string $modelLabel = 'periode penilaian';

    protected static ?string $pluralModelLabel = 'Periode Penilaian';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'penilaian/pengaturan/periode';

    protected static string $assessmentManagePermission = 'penilaian.period.manage';

    public static function canEdit(Model $record): bool
    {
        return $record instanceof AssessmentPeriod
            && static::canAccess()
            && $record->status === AssessmentPeriodStatus::DRAFT
            && static::canManageAssessment()
            && auth()->user()?->can('update', $record) === true;
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof AssessmentPeriod
            && static::canAccess()
            && $record->status === AssessmentPeriodStatus::DRAFT
            && ! $record->students()->exists()
            && static::canManageAssessment()
            && auth()->user()?->can('delete', $record) === true;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Identitas Periode')
                ->description('ASTS dan ASAS tersimpan terpisah. Status hanya berubah melalui aksi workflow.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\Select::make('assessment_academic_year_id')
                        ->label('Tahun Pelajaran')
                        ->relationship('academicYear', 'name')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(),
                    Forms\Components\Select::make('assessment_semester_id')
                        ->label('Semester')
                        ->options(fn (Get $get): array => Semester::query()
                            ->where('assessment_academic_year_id', $get('assessment_academic_year_id'))
                            ->orderBy('starts_on')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->required(),
                    Forms\Components\Select::make('type')
                        ->label('Jenis')
                        ->options(AssessmentType::options())
                        ->default(AssessmentType::ASTS->value)
                        ->live()
                        ->afterStateUpdated(function (Set $set, mixed $state): void {
                            $set(
                                'settings.collect_promotion_status',
                                $state === AssessmentType::ASAS->value,
                            );
                        })
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('code')
                        ->label('Kode Periode')
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Periode')
                        ->required()
                        ->maxLength(150)
                        ->columnSpanFull(),
                    Forms\Components\Hidden::make('status')
                        ->default(AssessmentPeriodStatus::DRAFT->value)
                        ->dehydrated(false),
                ]),
            Section::make('Kelas dan Jadwal')
                ->description('Snapshot hanya mengambil kelas yang dipilih. Setelah periode dibuka, master tidak lagi mengubah isi periode.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\Select::make('settings.rombel_ids')
                        ->label('Kelas Peserta')
                        ->options(fn (): array => Rombel::query()
                            ->where('is_active', true)
                            ->orderBy('nama')
                            ->pluck('nama', 'id')
                            ->all())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\DateTimePicker::make('entry_start_at')
                        ->label('Mulai Input Nilai')
                        ->seconds(false),
                    Forms\Components\DateTimePicker::make('entry_end_at')
                        ->label('Batas Input Nilai')
                        ->seconds(false)
                        ->after('entry_start_at'),
                    Forms\Components\DatePicker::make('report_date')
                        ->label('Tanggal Rapor'),
                    Forms\Components\TextInput::make('settings.report_place')
                        ->label('Tempat Terbit')
                        ->maxLength(100),
                    Forms\Components\Toggle::make('settings.collect_promotion_status')
                        ->label('Catat Status Semester')
                        ->default(false)
                        ->inline(false)
                        ->helperText('Aktifkan untuk kenaikan/kelulusan atau status akhir semester. Default aktif saat jenis ASAS dipilih.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari periode, kode, atau semester...',
            emptyStateHeading: 'Belum ada periode Penilaian',
            emptyStateDescription: 'Impor master resmi terlebih dahulu, lalu buat periode ASTS atau ASAS.'
        )
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['academicYear', 'semester'])
                ->withCount(['students', 'assignments']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Periode')
                    ->description(fn (AssessmentPeriod $record): string => $record->code)
                    ->searchable(['name', 'code'])
                    ->wrap(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof AssessmentType ? $state->label() : strtoupper((string) $state))
                    ->color(fn (mixed $state): string => ($state instanceof AssessmentType ? $state->value : $state) === 'asts' ? 'info' : 'warning'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof AssessmentPeriodStatus ? $state->label() : (string) $state),
                Tables\Columns\TextColumn::make('semester.name')
                    ->label('Semester')
                    ->visibleFrom('md')
                    ->wrap(),
                Tables\Columns\TextColumn::make('students_count')
                    ->label('Siswa')
                    ->numeric(),
                Tables\Columns\TextColumn::make('assignments_count')
                    ->label('Penugasan')
                    ->numeric()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('entry_end_at')
                    ->label('Batas Input')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->visibleFrom('lg'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Jenis')
                    ->options(AssessmentType::options()),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(AssessmentPeriodStatus::options()),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (AssessmentPeriod $record): bool => static::canEdit($record) && $record->status === AssessmentPeriodStatus::DRAFT),
                static::transitionAction(
                    'open_period',
                    'Buka Periode',
                    'heroicon-o-play',
                    'success',
                    AssessmentPeriodStatus::DRAFT,
                    'open',
                    CreateAssessmentPeriodSnapshotAction::class,
                )->requiresConfirmation()
                    ->modalDescription('Preflight akan memeriksa kelas, siswa, akun guru, wali kelas, skema, dan total bobot sebelum snapshot dibuat.'),
                static::transitionAction(
                    'close_entry',
                    'Tutup Input',
                    'heroicon-o-lock-closed',
                    'warning',
                    AssessmentPeriodStatus::OPEN,
                    'closeEntry',
                    CloseAssessmentEntryAction::class,
                )->requiresConfirmation(),
                static::transitionAction(
                    'start_verification',
                    'Mulai Verifikasi',
                    'heroicon-o-check-badge',
                    'info',
                    AssessmentPeriodStatus::ENTRY_CLOSED,
                    'startVerification',
                    StartAssessmentVerificationAction::class,
                )->requiresConfirmation(),
                static::transitionAction(
                    'lock_period',
                    'Kunci Periode',
                    'heroicon-o-lock-closed',
                    'danger',
                    AssessmentPeriodStatus::VERIFICATION,
                    'lock',
                    LockAssessmentPeriodAction::class,
                )->requiresConfirmation(),
                static::transitionAction(
                    'publish_period',
                    'Terbitkan',
                    'heroicon-o-paper-airplane',
                    'success',
                    AssessmentPeriodStatus::LOCKED,
                    'publish',
                    PublishAssessmentPeriodAction::class,
                )->requiresConfirmation(),
                Action::make('reopen')
                    ->label('Buka Koreksi')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->authorize('reopen')
                    ->visible(fn (AssessmentPeriod $record): bool => static::canManageAssessment()
                        && in_array($record->status, [AssessmentPeriodStatus::LOCKED, AssessmentPeriodStatus::PUBLISHED], true))
                    ->form([
                        Forms\Components\Select::make('assignment_ids')
                            ->label('Penugasan yang Dibuka')
                            ->options(fn (AssessmentPeriod $record): array => $record->assignments()
                                ->orderBy('rombel_name_snapshot')
                                ->orderBy('subject_name_snapshot')
                                ->get()
                                ->mapWithKeys(fn (AssessmentPeriodAssignment $assignment): array => [
                                    $assignment->getKey() => "{$assignment->rombel_name_snapshot} · {$assignment->subject_name_snapshot} · {$assignment->teacher_name_snapshot}",
                                ])
                                ->all())
                            ->multiple()
                            ->searchable()
                            ->required(),
                        Forms\Components\Textarea::make('reason')
                            ->label('Alasan Koreksi')
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000),
                    ])
                    ->action(fn (AssessmentPeriod $record, array $data) => static::runTransition(
                        fn () => app(ReopenAssessmentPeriodAction::class)->execute(
                            auth()->user(),
                            $record,
                            array_map('intval', $data['assignment_ids']),
                            $data['reason'],
                        ),
                        'Penugasan terpilih dibuka untuk koreksi.',
                    )),
                DeleteAction::make()
                    ->visible(fn (AssessmentPeriod $record): bool => static::canDelete($record)
                        && $record->status === AssessmentPeriodStatus::DRAFT
                        && ! $record->students()->exists())
                    ->databaseTransaction()
                    ->before(function (AssessmentPeriod $record): void {
                        $period = AssessmentPeriod::query()
                            ->whereKey($record->getKey())
                            ->lockForUpdate()
                            ->firstOrFail();

                        abort_unless(
                            $period->status === AssessmentPeriodStatus::DRAFT
                                && static::canDelete($period),
                            403,
                        );
                    }),
            ])
            ->bulkActions([]);
    }

    protected static function transitionAction(
        string $name,
        string $label,
        string $icon,
        string $color,
        AssessmentPeriodStatus $status,
        string $ability,
        string $actionClass,
    ): Action {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->authorize($ability)
            ->visible(fn (AssessmentPeriod $record): bool => static::canManageAssessment() && $record->status === $status)
            ->action(fn (AssessmentPeriod $record) => static::runTransition(
                fn () => app($actionClass)->execute(auth()->user(), $record),
                "{$label} berhasil.",
            ));
    }

    protected static function runTransition(callable $callback, string $successMessage): mixed
    {
        try {
            $result = $callback();
            Notification::make()->title($successMessage)->success()->send();

            return $result;
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->title('Aksi tidak dapat dijalankan')
                ->body($exception->getMessage())
                ->danger()
                ->duration(15000)
                ->send();

            return null;
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssessmentPeriods::route('/'),
            'create' => Pages\CreateAssessmentPeriod::route('/create'),
            'edit' => Pages\EditAssessmentPeriod::route('/{record}/edit'),
        ];
    }
}
