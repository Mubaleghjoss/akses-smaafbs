<?php

namespace App\Filament\Pages\Assessment;

use App\Models\User;
use Illuminate\Contracts\Support\Htmlable;

class QuestionBankBuilderPage extends AssessmentPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Bank & Penyusunan Soal';

    protected static ?string $slug = 'penilaian/penyusunan-soal';

    protected static ?int $navigationSort = 15;

    protected string $view = 'filament.pages.assessment.question-bank-builder';

    public function getTitle(): string|Htmlable
    {
        return 'Bank & Penyusunan Soal';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Rancangan awal penyusunan soal dari format dokumen menuju ujian online yang tervalidasi.';
    }

    public function canPrepareAdvancedFeatures(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->hasFullAdminAccess() || $user->hasRole('kurikulum'));
    }
}
