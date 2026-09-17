<?php

namespace App\Filament\Pages\Assessment;

use App\Models\Exam\AiSetting;
use App\Models\Exam\QuestionSet;
use App\Models\User;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

class QuestionBankBuilderPage extends AssessmentPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Bank & Penyusunan Soal';

    protected static ?string $slug = 'penilaian/penyusunan-soal';

    protected static ?int $navigationSort = 15;

    protected static string $assessmentPermission = 'penilaian.manage';

    protected string $view = 'filament.pages.assessment.question-bank-builder';

    public static function canAccess(): bool
    {
        if (parent::canAccess()) {
            return true;
        }

        $user = auth()->user();

        return config('assessment.enabled')
            && Schema::hasTable('assessment_periods')
            && $user instanceof User
            && $user->canManageModule('penilaian');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Bank & Penyusunan Soal';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Paket soal milik guru, editor butir, preview naskah, dan konfigurasi AI yang aman.';
    }

    public function questionSets(): Collection
    {
        if (! Schema::hasTable('exam_question_sets')) return new Collection();
        $query = QuestionSet::query()->with('questions')->latest();
        $user = auth()->user();
        if ($user instanceof User && ! $user->hasFullAdminAccess() && ! $user->hasRole('kurikulum')) $query->where('teacher_id', $user->id);
        return $query->get();
    }

    public function aiSetting(): ?AiSetting
    {
        return Schema::hasTable('exam_ai_settings') ? AiSetting::query()->first() : null;
    }

    public function schemaReady(): bool
    {
        return Schema::hasTable('exam_question_sets');
    }

    public function canPrepareAdvancedFeatures(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->hasFullAdminAccess() || $user->hasRole('kurikulum'));
    }
}
