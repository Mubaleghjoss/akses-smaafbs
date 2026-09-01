<?php

namespace App\Filament\Pages\Assessment;

use App\Models\User;
use App\Support\Assessment\AssessmentReportProgress;
use Illuminate\Contracts\Support\Htmlable;

class AssessmentReportProgressPage extends AssessmentPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Progres Rapor';

    protected static ?string $slug = 'penilaian/progres-rapor';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.assessment.report-progress';

    public function getTitle(): string|Htmlable
    {
        return 'Progres Rapor';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Pantau tanggung jawab dan langkah berikutnya sampai rapor pada semua periode aktif siap dicetak.';
    }

    /** @return array<int, array<string, mixed>> */
    public function getProgressPeriods(): array
    {
        $user = auth()->user();

        return $user instanceof User
            ? app(AssessmentReportProgress::class)->forUser($user)->all()
            : [];
    }

    /** @return array{key: string, title: string, description: string, action: string, icon: string} */
    public function getEmptyState(): array
    {
        $user = auth()->user();

        return $user instanceof User
            ? app(AssessmentReportProgress::class)->emptyStateForUser($user)
            : [
                'key' => 'unauthenticated',
                'title' => 'Sesi Anda Tidak Aktif',
                'description' => 'Silakan masuk kembali untuk melihat progres rapor.',
                'action' => 'Masuk kembali melalui halaman login.',
                'icon' => 'heroicon-o-lock-closed',
            ];
    }
}
