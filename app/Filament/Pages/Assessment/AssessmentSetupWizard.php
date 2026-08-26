<?php

namespace App\Filament\Pages\Assessment;

use App\Filament\Resources\AssessmentPeriodResource;
use App\Filament\Resources\AssessmentSchemeResource;
use App\Filament\Resources\AssessmentSubjectCategoryResource;
use App\Filament\Resources\AssessmentSubjectResource;
use App\Models\Assessment\Semester;
use App\Support\Assessment\AssessmentSetupStatus;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url as UrlAttribute;

/**
 * Satu pintu SETELAN AWAL penilaian.
 *
 * Sebelumnya admin harus menyentuh 7 tempat berbeda tanpa satu pun halaman yang
 * memberi tahu langkah mana yang belum selesai. Halaman ini TIDAK menggantikan
 * halaman yang ada — ia merangkumnya: menampilkan status tiap langkah, APA yang
 * kurang, dan tombol langsung ke tempatnya.
 */
class AssessmentSetupWizard extends AssessmentPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Setelan Awal Penilaian';

    protected static ?string $slug = 'penilaian/setelan-awal';

    protected static ?int $navigationSort = 0;

    protected static string $assessmentPermission = 'penilaian.manage';

    protected string $view = 'filament.pages.assessment.setup-wizard';

    #[UrlAttribute(as: 'semester')]
    public ?int $semesterId = null;

    public function mount(): void
    {
        $this->authorizeAssessment('penilaian.manage');

        $this->semesterId ??= app(AssessmentSetupStatus::class)->semesterBawaan()?->getKey();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Setelan Awal Penilaian';
    }

    /**
     * @return array<int|string, string>
     */
    public function getSemesterOptions(): array
    {
        return Semester::query()
            ->with('academicYear')
            ->latest('starts_on')
            ->get()
            ->mapWithKeys(fn (Semester $s): array => [
                $s->getKey() => trim(($s->academicYear?->name ?? '-').' · '.$s->name),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSetupStatus(): array
    {
        return app(AssessmentSetupStatus::class)->untukSemester(
            $this->semesterId ? Semester::with('academicYear')->find($this->semesterId) : null,
        );
    }

    /**
     * Tautan tujuan tiap langkah. Dipisah dari pemeriksa status supaya
     * AssessmentSetupStatus tetap murni membaca data, tanpa tahu soal UI.
     *
     * @return array<int, array{label: string, url: string|null}>
     */
    public function getStepLinks(): array
    {
        return [
            1 => [
                'label' => 'Kelola Periode & Semester',
                'url' => AssessmentPeriodResource::canViewAny() ? AssessmentPeriodResource::getUrl() : null,
            ],
            2 => [
                'label' => 'Kelola Mapel',
                'url' => AssessmentSubjectResource::canViewAny() ? AssessmentSubjectResource::getUrl() : null,
            ],
            3 => [
                'label' => 'Atur Matriks Kelas × Mapel',
                'url' => AssessmentTeachingMatrix::canAccess()
                    ? AssessmentTeachingMatrix::getUrl(['semester' => $this->semesterId])
                    : null,
            ],
            4 => [
                'label' => 'Atur Wali Kelas',
                'url' => AssessmentTeachingMatrix::canAccess()
                    ? AssessmentTeachingMatrix::getUrl(['semester' => $this->semesterId, 'tab' => 'wali'])
                    : null,
            ],
            5 => [
                'label' => 'Kelola Skema & Komponen',
                'url' => AssessmentSchemeResource::canViewAny() ? AssessmentSchemeResource::getUrl() : null,
            ],
            6 => [
                'label' => 'Buka Periode',
                'url' => AssessmentPeriodResource::canViewAny() ? AssessmentPeriodResource::getUrl() : null,
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, url: string|null}>
     */
    public function getExtraLinks(): array
    {
        return array_values(array_filter([
            AssessmentSubjectCategoryResource::canViewAny()
                ? ['label' => 'Kategori Mapel', 'url' => AssessmentSubjectCategoryResource::getUrl()]
                : null,
            ['label' => 'Pengaturan Penilaian', 'url' => AssessmentDashboard::getUrl()],
        ]));
    }
}
