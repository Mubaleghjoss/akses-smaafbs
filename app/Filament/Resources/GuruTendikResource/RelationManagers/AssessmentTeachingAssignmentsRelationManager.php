<?php

namespace App\Filament\Resources\GuruTendikResource\RelationManagers;

use App\Models\Assessment\Semester;
use App\Models\Assessment\Subject;
use App\Models\Assessment\SubjectCategory;
use App\Models\Assessment\TeachingAssignment;
use App\Models\Rombel;
use App\Support\Assessment\AssessmentAuditLogger;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Validation\Rule;

class AssessmentTeachingAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assessmentTeachingAssignments';

    protected static ?string $title = 'Mapel dan Kelas Mengajar';

    protected static bool $isLazy = true;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return DatabaseSchema::hasTable('assessment_teaching_assignments')
            && DatabaseSchema::hasTable('assessment_subjects')
            && DatabaseSchema::hasTable('assessment_semesters')
            && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Penugasan Guru Mapel')
                ->description('Pilih semester, mata pelajaran, dan kelas. Data ini dipakai saat periode ASTS/ASAS berikutnya dibuka.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\Select::make('assessment_semester_id')
                        ->label('Semester')
                        ->options(static::semesterOptions())
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->live()
                        ->required(),
                    Forms\Components\Select::make('assessment_subject_id')
                        ->label('Mata Pelajaran')
                        ->options(static::subjectOptions())
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->live()
                        ->required(),
                    Forms\Components\Select::make('assessment_subject_category_id')
                        ->label('Kategori Rapor')
                        ->options(static::categoryOptions())
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->required(),
                    Forms\Components\Select::make('rombel_id')
                        ->label('Kelas / Rombel')
                        ->options(static::rombelOptions())
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->rules([
                            fn (Get $get, ?TeachingAssignment $record) => Rule::unique('assessment_teaching_assignments', 'rombel_id')
                                ->where(fn ($query) => $query
                                    ->where('assessment_semester_id', $get('assessment_semester_id'))
                                    ->where('assessment_subject_id', $get('assessment_subject_id'))
                                    ->where('is_active', true))
                                ->ignore($record),
                        ])
                        ->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Penugasan Aktif')
                        ->helperText('Nonaktifkan bila penugasan tidak dipakai lagi. Riwayat tetap tersimpan.')
                        ->default(true)
                        ->inline(false),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Mapel dan Kelas Mengajar')
            ->description('Satu baris berarti guru mengampu satu mata pelajaran pada satu kelas dalam satu semester.')
            ->defaultSort('assessment_semester_id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('subject.name')
                    ->label('Mata Pelajaran')
                    ->description(fn (TeachingAssignment $record): string => $record->semester?->name ?? '-')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('rombel.nama')
                    ->label('Kelas')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori Rapor')
                    ->badge()
                    ->color(fn (TeachingAssignment $record): string => $record->category?->type === SubjectCategory::TYPE_WAJIB ? 'warning' : 'info')
                    ->placeholder('Belum dipilih')
                    ->wrap(),
                Tables\Columns\TextColumn::make('semester.academicYear.name')
                    ->label('Tahun Pelajaran')
                    ->placeholder('-')
                    ->visibleFrom('md'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Mapel & Kelas')
                    ->icon('heroicon-o-plus')
                    ->createAnother(false)
                    ->authorize(fn (): bool => Gate::allows('create', TeachingAssignment::class))
                    ->before(fn () => Gate::authorize('create', TeachingAssignment::class))
                    ->mutateDataUsing(fn (array $data): array => $this->withSnapshots($data))
                    ->after(fn (TeachingAssignment $record) => $this->audit(
                        'teaching_assignment.created',
                        $record,
                    ))
                    ->databaseTransaction(),
            ])
            ->actions([
                EditAction::make()
                    ->authorize(fn (TeachingAssignment $record): bool => Gate::allows('update', $record))
                    ->before(fn (TeachingAssignment $record) => Gate::authorize('update', $record))
                    ->mutateDataUsing(fn (array $data): array => $this->withSnapshots($data))
                    ->after(fn (TeachingAssignment $record) => $this->audit(
                        'teaching_assignment.updated',
                        $record,
                    ))
                    ->databaseTransaction(),
                DeleteAction::make()
                    ->authorize(fn (TeachingAssignment $record): bool => Gate::allows('delete', $record))
                    ->before(fn (TeachingAssignment $record) => Gate::authorize('delete', $record))
                    ->after(fn (TeachingAssignment $record) => $this->audit(
                        'teaching_assignment.deleted',
                        $record,
                    ))
                    ->databaseTransaction(),
            ])
            ->emptyStateIcon('heroicon-o-book-open')
            ->emptyStateHeading('Belum ada mapel dan kelas')
            ->emptyStateDescription('Tambahkan semester, mapel, dan kelas yang diampu guru ini.')
            ->bulkActions([]);
    }

    /**
     * @return array<int, string>
     */
    private static function semesterOptions(): array
    {
        return Semester::query()
            ->with('academicYear:id,name')
            ->latest('id')
            ->get()
            ->mapWithKeys(fn (Semester $semester): array => [
                $semester->getKey() => trim(($semester->academicYear?->name ?? '').' · '.$semester->name)
                    .($semester->is_active ? ' · Aktif' : ''),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function subjectOptions(): array
    {
        return Subject::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Subject $subject): array => [
                $subject->getKey() => $subject->name.($subject->is_active ? '' : ' · Nonaktif'),
            ])
            ->all();
    }

    /** @return array<int, string> */
    private static function categoryOptions(): array
    {
        return SubjectCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function rombelOptions(): array
    {
        return Rombel::query()
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn (Rombel $rombel): array => [
                $rombel->getKey() => $rombel->nama.($rombel->is_active ? '' : ' · Nonaktif'),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withSnapshots(array $data): array
    {
        $subject = Subject::query()->findOrFail((int) ($data['assessment_subject_id'] ?? 0));
        $rombel = Rombel::query()->findOrFail((int) ($data['rombel_id'] ?? 0));

        return [
            ...$data,
            'teacher_name_snapshot' => (string) $this->getOwnerRecord()->nama,
            'subject_name_snapshot' => (string) $subject->name,
            'rombel_name_snapshot' => (string) $rombel->nama,
        ];
    }

    private function audit(string $event, TeachingAssignment $record): void
    {
        app(AssessmentAuditLogger::class)->record(
            actor: auth()->user(),
            event: $event,
            subject: $record,
            newValues: $record->only([
                'assessment_semester_id',
                'assessment_subject_id',
                'assessment_subject_category_id',
                'teacher_id',
                'rombel_id',
                'teacher_name_snapshot',
                'subject_name_snapshot',
                'rombel_name_snapshot',
                'is_active',
            ]),
        );
    }
}
