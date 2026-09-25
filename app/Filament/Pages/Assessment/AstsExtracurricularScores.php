<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;
use App\Exports\AssessmentExtracurricularImportTemplateExport;
use App\Filament\Pages\Assessment\Concerns\HasAssessmentTypeNavigation;
use App\Models\Assessment\AssessmentExtracurricular;
use App\Models\Assessment\AssessmentExtracurricularParticipant;
use App\Models\Assessment\AssessmentExtracurricularScore;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\User;
use App\Support\Assessment\AssessmentExtracurricularImport;
use App\Support\Assessment\AssessmentExtracurricularWorkflow;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class AstsExtracurricularScores extends AssessmentPage
{
    use HasAssessmentTypeNavigation;
    use WithFileUploads;

    protected static AssessmentType $assessmentType = AssessmentType::ASTS;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';
    protected static ?string $navigationLabel = 'Nilai Ekskul Saya';
    protected static ?string $slug = 'penilaian/asts/nilai-ekskul';
    protected static ?int $navigationSort = 2;
    protected static string $assessmentPermission = 'penilaian.input';
    protected string $view = 'filament.pages.assessment.asts-extracurricular-scores';

    #[Url(as: 'period')]
    public ?int $periodId = null;
    public string $newName = '';
    public string $newCode = '';
    public ?int $newTeacherId = null;
    public ?int $selectedExtracurricularId = null;
    public ?int $selectedStudentId = null;
    public ?int $selectedRombelId = null;
    /** @var array<int, int> */
    public array $selectedStudentIds = [];
    public string $participantStatusFilter = 'all';
    public string $participantSearch = '';
    public mixed $importFile = null;
    /** @var array<int, string|null> */
    public array $predicates = [];
    /** @var array<int, string|null> */
    public array $descriptions = [];
    /** @var array<int, string> */
    public array $returnReasons = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! config('assessment.enabled') || ! Schema::hasTable('assessment_extracurriculars') || ! $user instanceof User || ! $user->canViewModule('penilaian')) {
            return false;
        }

        return $user->hasFullAdminAccess() || $user->hasRole('kurikulum') || $user->can('penilaian.verify') || $user->can('penilaian.input');
    }

    public function mount(): void
    {
        $this->periodId = $this->periodId && array_key_exists($this->periodId, $this->getPeriodOptions()) ? $this->periodId : array_key_first($this->getPeriodOptions());
        $this->loadScores();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->isManager() ? 'Pengelolaan Nilai Ekskul ASTS' : 'Nilai Ekskul Saya';
    }

    public function getPeriodOptions(): array
    {
        return AssessmentPeriod::query()->where('type', AssessmentType::ASTS->value)->latest('id')->pluck('name', 'id')->all();
    }

    public function updatedPeriodId(): void
    {
        $this->selectedExtracurricularId = null;
        $this->loadScores();
    }

    public function updatedSelectedExtracurricularId(): void
    {
        $this->loadScores();
    }

    public function updatedSelectedRombelId(): void
    {
        $this->selectedStudentIds = [];
    }

    public function updatedParticipantStatusFilter(): void
    {
        $this->loadScores();
    }

    public function updatedParticipantSearch(): void
    {
        $this->loadScores();
    }

    public function isManager(): bool
    {
        $user = auth()->user();
        return $user instanceof User && ($user->hasFullAdminAccess() || $user->hasRole('kurikulum') || $user->can('penilaian.verify'));
    }

    public function getExtracurricularsProperty()
    {
        $user = auth()->user();
        return AssessmentExtracurricular::query()->where('assessment_period_id', $this->periodId)->when(! $this->isManager(), fn (Builder $query) => $query->whereHas('teachers', fn (Builder $teachers) => $teachers->whereKey($user->getKey())))
            ->with('teachers:id,name')
            ->withCount([
                'participants',
                'participants as draft_count' => fn (Builder $query) => $query->whereDoesntHave('score')->orWhereHas('score', fn (Builder $score) => $score->where('status', 'draft')),
                'participants as submitted_count' => fn (Builder $query) => $query->whereHas('score', fn (Builder $score) => $score->where('status', 'submitted')),
                'participants as verified_count' => fn (Builder $query) => $query->whereHas('score', fn (Builder $score) => $score->where('status', 'verified')),
                'participants as returned_count' => fn (Builder $query) => $query->whereHas('score', fn (Builder $score) => $score->where('status', 'returned')),
            ])
            ->orderBy('name')->get();
    }

    public function getSummaryProperty(): array
    {
        $activities = $this->extracurriculars;

        return [
            'active' => $activities->where('is_active', true)->count(),
            'participants' => (int) $activities->sum('participants_count'),
            'draft' => (int) $activities->sum('draft_count'),
            'submitted' => (int) $activities->sum('submitted_count'),
            'verified' => (int) $activities->sum('verified_count'),
            'returned' => (int) $activities->sum('returned_count'),
        ];
    }

    public function getParticipantsProperty()
    {
        if (! $this->selectedExtracurricularId || ! $this->extracurriculars->contains('id', $this->selectedExtracurricularId)) {
            return collect();
        }

        return AssessmentExtracurricularParticipant::query()->where('assessment_extracurricular_id', $this->selectedExtracurricularId)
            ->with(['student', 'score'])->whereHas('student', function (Builder $query): void {
                $query->where('is_active', true)
                    ->when(filled($this->participantSearch), fn (Builder $students) => $students->where(fn (Builder $search) => $search
                        ->where('student_name_snapshot', 'like', '%'.trim($this->participantSearch).'%')
                        ->orWhere('rombel_name_snapshot', 'like', '%'.trim($this->participantSearch).'%')));
            })
            ->when($this->participantStatusFilter === 'draft', fn (Builder $query) => $query->whereHas('score', fn (Builder $score) => $score->where('status', 'draft')))
            ->when($this->participantStatusFilter === 'unscored', fn (Builder $query) => $query->whereDoesntHave('score'))
            ->when(in_array($this->participantStatusFilter, ['submitted', 'verified', 'returned'], true), fn (Builder $query) => $query->whereHas('score', fn (Builder $score) => $score->where('status', $this->participantStatusFilter)))
            ->get()
            ->sortBy(fn ($row) => $row->student->rombel_name_snapshot.'|'.$row->student->student_name_snapshot)->values();
    }

    public function loadScores(): void
    {
        $this->predicates = [];
        $this->descriptions = [];
        foreach ($this->participants as $participant) {
            $this->predicates[$participant->id] = $participant->score?->predicate;
            $this->descriptions[$participant->id] = $participant->score?->description;
        }
    }

    public function createExtracurricular(): void
    {
        abort_unless($this->isManager(), 403);
        $data = $this->validate(['newName' => ['required', 'string', 'max:150'], 'newCode' => ['nullable', 'string', 'max:60'], 'newTeacherId' => ['nullable', 'integer', 'exists:users,id']]);
        $activity = AssessmentExtracurricular::query()->create(['assessment_period_id' => $this->periodId, 'name' => trim($data['newName']), 'code' => filled($data['newCode']) ? trim($data['newCode']) : null, 'is_active' => true, 'created_by' => auth()->id()]);
        if ($data['newTeacherId']) {
            $activity->teachers()->syncWithoutDetaching([$data['newTeacherId']]);
        }
        $this->newName = $this->newCode = '';
        $this->newTeacherId = null;
        $this->selectedExtracurricularId = $activity->id;
        Notification::make()->success()->title('Ekskul dibuat')->send();
    }

    public function assignTeacher(int $extracurricularId, int $teacherId): void
    {
        abort_unless($this->isManager(), 403);
        $this->managerActivity($extracurricularId)->teachers()->syncWithoutDetaching([$teacherId]);
    }

    public function addParticipant(): void
    {
        abort_unless($this->isManager(), 403);
        $data = $this->validate(['selectedExtracurricularId' => ['required', 'integer'], 'selectedStudentId' => ['required', 'integer']]);
        $activity = $this->managerActivity($data['selectedExtracurricularId']);
        $student = AssessmentPeriodStudent::query()->where('assessment_period_id', $this->periodId)->where('is_active', true)->findOrFail($data['selectedStudentId']);
        $created = $this->addStudentsToActivity($activity, collect([$student]));
        $this->selectedStudentId = null;
        $this->notifyParticipantResult($created, 1 - $created);
    }

    public function addSelectedParticipants(): void
    {
        abort_unless($this->isManager(), 403);
        $data = $this->validate([
            'selectedExtracurricularId' => ['required', 'integer'],
            'selectedRombelId' => ['required', 'integer'],
            'selectedStudentIds' => ['required', 'array', 'min:1'],
            'selectedStudentIds.*' => ['integer'],
        ]);
        $activity = $this->managerActivity($data['selectedExtracurricularId']);
        $students = AssessmentPeriodStudent::query()->where('assessment_period_id', $this->periodId)
            ->where('assessment_period_rombel_id', $data['selectedRombelId'])->where('is_active', true)
            ->whereIn('id', $data['selectedStudentIds'])->get();
        $created = $this->addStudentsToActivity($activity, $students);
        $this->selectedStudentIds = [];
        $this->notifyParticipantResult($created, $students->count() - $created);
    }

    public function selectAllClassStudents(): void
    {
        $this->selectedStudentIds = $this->classStudentOptions->pluck('id')->all();
    }

    private function addStudentsToActivity(AssessmentExtracurricular $activity, $students): int
    {
        $created = 0;
        foreach ($students as $student) {
            $participant = AssessmentExtracurricularParticipant::query()->firstOrCreate(
                ['assessment_extracurricular_id' => $activity->id, 'assessment_period_student_id' => $student->id],
                ['assessment_period_rombel_id' => $student->assessment_period_rombel_id, 'source' => 'manual']
            );
            $created += $participant->wasRecentlyCreated ? 1 : 0;
        }
        $this->loadScores();
        return $created;
    }

    private function notifyParticipantResult(int $created, int $skipped): void
    {
        Notification::make()->success()->title("{$created} peserta ditambahkan".($skipped ? ", {$skipped} sudah terdaftar" : ''))->send();
    }

    public function downloadImportTemplate()
    {
        abort_unless($this->isManager(), 403);
        if (! $this->periodId) {
            Notification::make()->danger()->title('Pilih periode ASTS terlebih dahulu.')->send();

            return null;
        }

        $period = AssessmentPeriod::query()->where('type', AssessmentType::ASTS->value)->findOrFail($this->periodId);
        $filename = 'template-peserta-ekskul-asts-'.Str::slug($period->code ?: $period->name).'.xlsx';

        return Excel::download(new AssessmentExtracurricularImportTemplateExport($period), $filename);
    }

    public function importParticipants(): void
    {
        abort_unless($this->isManager(), 403);
        $this->validate(['importFile' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120']]);
        $result = app(AssessmentExtracurricularImport::class)->import(AssessmentPeriod::findOrFail($this->periodId), $this->importFile, auth()->id());
        $this->importFile = null;
        $message = "Import selesai: {$result['created']} peserta, {$result['skipped']} dilewati";
        if ($result['scores_updated'] > 0 || $result['protected_scores'] > 0) {
            $message .= "; {$result['scores_updated']} draf nilai disimpan, {$result['protected_scores']} nilai terkunci tidak ditimpa";
        }
        Notification::make()->success()->title($message)->send();
    }

    public function saveScore(int $participantId): void
    {
        $participant = $this->scopedParticipant($participantId);
        app(AssessmentExtracurricularWorkflow::class)->save(auth()->user(), $participant, $this->predicates[$participantId] ?? null, $this->descriptions[$participantId] ?? null);
        Notification::make()->success()->title('Draf nilai disimpan')->send();
        $this->loadScores();
    }

    public function submitScore(int $participantId): void
    {
        app(AssessmentExtracurricularWorkflow::class)->submit(auth()->user(), $this->scopedParticipant($participantId));
        Notification::make()->success()->title('Nilai dikirim untuk verifikasi')->send();
        $this->loadScores();
    }

    public function verifyScore(int $scoreId): void
    {
        abort_unless($this->isManager(), 403);
        $score = AssessmentExtracurricularScore::query()->whereHas('participant.extracurricular', fn (Builder $query) => $query->where('assessment_period_id', $this->periodId))->findOrFail($scoreId);
        app(AssessmentExtracurricularWorkflow::class)->verify(auth()->user(), $score);
        $this->loadScores();
    }

    public function returnScore(int $scoreId): void
    {
        abort_unless($this->isManager(), 403);
        $score = AssessmentExtracurricularScore::query()->whereHas('participant.extracurricular', fn (Builder $query) => $query->where('assessment_period_id', $this->periodId))->findOrFail($scoreId);
        app(AssessmentExtracurricularWorkflow::class)->return(auth()->user(), $score, $this->returnReasons[$scoreId] ?? '');
        $this->loadScores();
    }

    public function getTeacherOptionsProperty(): array
    {
        return User::query()->whereNotNull('guru_tendik_id')->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getStudentOptionsProperty(): array
    {
        return AssessmentPeriodStudent::query()->where('assessment_period_id', $this->periodId)->where('is_active', true)->orderBy('rombel_name_snapshot')->orderBy('student_name_snapshot')->get()->mapWithKeys(fn ($student) => [$student->id => $student->rombel_name_snapshot.' - '.$student->student_name_snapshot])->all();
    }

    public function getRombelOptionsProperty(): array
    {
        return AssessmentPeriodRombel::query()->where('assessment_period_id', $this->periodId)->where('is_active', true)->orderBy('rombel_name_snapshot')->pluck('rombel_name_snapshot', 'id')->all();
    }

    public function getClassStudentOptionsProperty()
    {
        return AssessmentPeriodStudent::query()->where('assessment_period_id', $this->periodId)->where('assessment_period_rombel_id', $this->selectedRombelId)->where('is_active', true)->orderBy('student_name_snapshot')->get(['id', 'student_name_snapshot', 'nis_snapshot']);
    }

    private function scopedParticipant(int $id): AssessmentExtracurricularParticipant
    {
        $allowed = $this->extracurriculars->pluck('id');
        return AssessmentExtracurricularParticipant::query()->whereIn('assessment_extracurricular_id', $allowed)->with(['extracurricular.teachers', 'score'])->findOrFail($id);
    }

    private function managerActivity(int $id): AssessmentExtracurricular
    {
        return AssessmentExtracurricular::query()->where('assessment_period_id', $this->periodId)->findOrFail($id);
    }
}
