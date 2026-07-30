<?php

namespace App\Filament\Pages\Assessment;

use App\Support\AssessmentMaster\AssessmentMasterWorkbookImporter;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Throwable;

/**
 * @property-read Schema $form
 */
class AssessmentMasterImport extends AssessmentPage implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Impor Master Resmi';

    protected static ?string $slug = 'penilaian/pengaturan/impor-master';

    protected static ?int $navigationSort = 14;

    protected static string $assessmentPermission = 'penilaian.period.manage';

    protected string $view = 'filament.pages.assessment.master-import';

    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $preview = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Impor Master Penilaian';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Pratinjau perubahan tahun, semester, mapel, guru pengampu, dan wali kelas sebelum menyentuh database.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Workbook Master Resmi')
                    ->description('Baris yang tidak ada di Excel tidak akan dihapus atau dinonaktifkan.')
                    ->columns(1)
                    ->schema([
                        Forms\Components\Placeholder::make('download_template')
                            ->label('Template')
                            ->content(new HtmlString(
                                '<a href="'.route('admin.assessment.master-template', absolute: false).'" target="_blank" rel="noopener noreferrer" data-navigate="false" class="font-semibold text-primary-600 underline">Download template Excel ASTS–ASAS</a>'
                            )),
                        Forms\Components\FileUpload::make('workbook')
                            ->label('File workbook')
                            ->disk('local')
                            ->directory('assessment-imports')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ])
                            ->maxSize(5120)
                            ->required()
                            ->helperText('Format .xlsx, maksimal 5 MB. File disimpan privat dan dihapus setelah impor berhasil.'),
                    ]),
            ]);
    }

    public function previewImport(): void
    {
        $this->authorizeAssessment('penilaian.period.manage');
        $state = $this->form->getState();
        $path = $this->resolveWorkbookPath($state['workbook'] ?? null);

        if (! $path) {
            Notification::make()->title('File workbook tidak ditemukan')->danger()->send();
            return;
        }

        try {
            $this->preview = app(AssessmentMasterWorkbookImporter::class)->preview($path);
        } catch (Throwable $exception) {
            report($exception);
            $this->preview = null;
            Notification::make()
                ->title('Workbook gagal dibaca')
                ->body($exception->getMessage())
                ->danger()
                ->send();
            return;
        }

        Notification::make()
            ->title(($this->preview['errors'] ?? []) === [] ? 'Pratinjau siap diperiksa' : 'Pratinjau memiliki kesalahan')
            ->color(($this->preview['errors'] ?? []) === [] ? 'success' : 'danger')
            ->send();
    }

    public function applyImport(): void
    {
        $this->authorizeAssessment('penilaian.period.manage');

        if (! $this->preview) {
            Notification::make()->title('Buat pratinjau terlebih dahulu')->warning()->send();
            return;
        }

        try {
            $result = app(AssessmentMasterWorkbookImporter::class)->apply(
                $this->preview,
                auth()->id(),
            );
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->title('Impor tidak dapat diterapkan')
                ->body($exception->getMessage())
                ->danger()
                ->send();
            return;
        }

        $workbook = $this->data['workbook'] ?? null;
        if (is_string($workbook) && Storage::disk('local')->exists($workbook)) {
            Storage::disk('local')->delete($workbook);
        }

        $this->preview = null;
        $this->form->fill();

        Notification::make()
            ->title('Master Penilaian berhasil diperbarui')
            ->body("{$result['created']} baru, {$result['updated']} berubah, {$result['unchanged']} tetap.")
            ->success()
            ->duration(12000)
            ->send();
    }

    protected function resolveWorkbookPath(mixed $state): ?string
    {
        if (is_array($state)) {
            $state = collect($state)->first();
        }

        if (is_object($state) && method_exists($state, 'getRealPath')) {
            $path = $state->getRealPath();
            return is_string($path) && is_file($path) ? $path : null;
        }

        if (! is_string($state) || ! Storage::disk('local')->exists($state)) {
            return null;
        }

        return Storage::disk('local')->path($state);
    }
}
