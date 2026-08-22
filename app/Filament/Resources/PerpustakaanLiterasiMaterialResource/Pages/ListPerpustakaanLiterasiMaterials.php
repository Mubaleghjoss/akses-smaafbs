<?php

namespace App\Filament\Resources\PerpustakaanLiterasiMaterialResource\Pages;

use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use App\Filament\Widgets\PerpustakaanLiterasiGlobalAnalytics;
use App\Jobs\QueueLiteracySimilarityReanalysis;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiResponse;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListPerpustakaanLiterasiMaterials extends ListRecords
{
    protected static string $resource = PerpustakaanLiterasiMaterialResource::class;

    #[Url(as: 'status')]
    public string $materialStatus = 'active';

    public function getTabs(): array
    {
        return [
            PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION => Tab::make('Literacy Habituation')
                ->query(fn (Builder $query): Builder => $this->statusWindowQuery($query)
                    ->where('program_category', PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION)),
            PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE => Tab::make('Numeracy Excellence')
                ->query(fn (Builder $query): Builder => $this->statusWindowQuery($query)
                    ->where('program_category', PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE)),
            PerpustakaanLiterasiMaterial::CATEGORY_SIGAP_29_KARAKTER => Tab::make('SIGAP 29 Karakter')
                ->query(fn (Builder $query): Builder => $this->statusWindowQuery($query)
                    ->where('program_category', PerpustakaanLiterasiMaterial::CATEGORY_SIGAP_29_KARAKTER)),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                SchemaView::make('filament.resources.perpustakaan-literasi-material-resource.partials.material-status-tabs')
                    ->viewData(fn (): array => [
                        'activeStatus' => $this->normalizedMaterialStatus(),
                        'statusCounts' => $this->materialStatusCounts(),
                    ]),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    public function setMaterialStatus(string $status): void
    {
        if (! in_array($status, ['active', 'inactive'], true)) {
            return;
        }

        $this->materialStatus = $status;
        $this->resetPage();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reanalyzeAllSimilarity')
                ->label('Hitung Ulang Plagiasi')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Hitung ulang plagiasi semua materi?')
                ->modalDescription('Sistem akan menghitung ulang indikasi plagiasi untuk semua jawaban literasi sesuai pengaturan soal terbaru.')
                ->modalSubmitActionLabel('Hitung Ulang')
                ->visible(fn (): bool => PerpustakaanLiterasiMaterialResource::canCreate())
                ->action(function (): void {
                    $total = PerpustakaanLiterasiResponse::query()->count();

                    QueueLiteracySimilarityReanalysis::dispatch();

                    Notification::make()
                        ->title('Hitung ulang plagiasi masuk antrean')
                        ->body(number_format($total, 0, ',', '.').' responden akan dianalisa bertahap di background.')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('configureDefaultInstructions')
                ->label('Setting Tatib')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->visible(fn (): bool => PerpustakaanLiterasiMaterialResource::canCreate())
                ->fillForm(fn (): array => [
                    'instructions' => PerpustakaanLiterasiMaterial::defaultInstructionsText(),
                ])
                ->form([
                    Forms\Components\Textarea::make('instructions')
                        ->label('Arahan / Tatib Default')
                        ->rows(7)
                        ->required()
                        ->helperText('Dipakai di halaman daftar Literasi Numerasi dan menjadi fallback untuk materi yang belum punya tatib khusus.'),
                ])
                ->modalHeading('Setting Tatib Literasi Numerasi')
                ->modalSubmitActionLabel('Simpan Tatib')
                ->action(function (array $data): void {
                    PerpustakaanLiterasiMaterial::saveDefaultInstructions((string) ($data['instructions'] ?? ''));

                    Notification::make()
                        ->title('Tatib default Literasi Numerasi diperbarui.')
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make()
                ->fillForm(fn (): array => $this->activeProgramCategory() !== null
                    ? ['program_category' => $this->activeProgramCategory()]
                    : []),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PerpustakaanLiterasiGlobalAnalytics::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function activeProgramCategory(): ?string
    {
        $activeTab = (string) data_get($this, 'activeTab', '');

        return array_key_exists($activeTab, PerpustakaanLiterasiMaterial::programCategoryOptions())
            ? $activeTab
            : null;
    }

    /**
     * @return array{active: int, inactive: int}
     */
    protected function materialStatusCounts(): array
    {
        $category = $this->activeProgramCategory()
            ?? PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION;
        $query = PerpustakaanLiterasiMaterial::query()
            ->where('program_category', $category);

        return [
            'active' => $this->activeWindowQuery(clone $query)->count(),
            'inactive' => $this->inactiveWindowQuery(clone $query)->count(),
        ];
    }

    protected function normalizedMaterialStatus(): string
    {
        return $this->materialStatus === 'inactive' ? 'inactive' : 'active';
    }

    protected function statusWindowQuery(Builder $query): Builder
    {
        return $this->normalizedMaterialStatus() === 'inactive'
            ? $this->inactiveWindowQuery($query)
            : $this->activeWindowQuery($query);
    }

    protected function activeWindowQuery(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $inner): void {
                $inner->whereNull('closes_at')
                    ->orWhere('closes_at', '>=', now());
            });
    }

    protected function inactiveWindowQuery(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->where('is_active', false)
                ->orWhere(function (Builder $expired): void {
                    $expired->where('is_active', true)
                        ->whereNotNull('closes_at')
                        ->where('closes_at', '<', now());
                });
        });
    }
}
