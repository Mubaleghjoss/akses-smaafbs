<?php

namespace App\Filament\Pages\Assessment;

use App\Models\Exam\Attempt;
use App\Models\Exam\QuestionSet;
use App\Models\Exam\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

class OnlineExamPage extends AssessmentPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-computer-desktop';
    protected static ?string $navigationLabel = 'Ujian Online';
    protected static ?string $slug = 'penilaian/ujian-online';
    protected static ?int $navigationSort = 16;
    protected string $view = 'filament.pages.assessment.online-exam';

    public function schemaReady(): bool { return Schema::hasTable('exam_schedules'); }
    public function sets(): Collection { return $this->owned(QuestionSet::query()->with('questions'))->get(); }
    public function schedules(): Collection { return $this->owned(Schedule::query()->with(['questionSet', 'attempts', 'tokens']), 'questionSet')->latest()->get(); }
    public function attempts(): Collection { return $this->owned(Attempt::query()->with(['studentToken', 'schedule.questionSet', 'answers.question', 'events']), 'schedule.questionSet')->latest()->get(); }

    /** @return array{total:int,verified:int,started:int,submitted:int,attention:int} */
    public function monitoringSummary(): array
    {
        $attempts = $this->attempts();

        return [
            'total' => $attempts->count(),
            'verified' => $attempts->filter(fn (Attempt $attempt) => $attempt->studentToken->verified_at !== null)->count(),
            'started' => $attempts->filter(fn (Attempt $attempt) => $attempt->started_at !== null)->count(),
            'submitted' => $attempts->where('status', 'submitted')->count(),
            'attention' => $attempts->filter(fn (Attempt $attempt) => $attempt->exit_count > 0 || $attempt->offline_count > 0)->count(),
        ];
    }

    private function owned($query, string $relation = '')
    {
        if (! $this->schemaReady()) return $query->whereRaw('1=0');
        $user = auth()->user();
        if ($user instanceof User && ! $user->hasFullAdminAccess() && ! $user->hasRole('kurikulum')) {
            $relation === '' ? $query->where('teacher_id', $user->id) : $query->whereHas($relation, fn ($q) => $q->where('teacher_id', $user->id));
        }
        return $query;
    }
}
