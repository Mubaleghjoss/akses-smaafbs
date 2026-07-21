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
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPerpustakaanLiterasiMaterials extends ListRecords
{
    protected static string $resource = PerpustakaanLiterasiMaterialResource::class;

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Semua Aktif')
                ->query(fn (Builder $query): Builder => $this->activeWindowQuery($query)),
            PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION => Tab::make('Literacy Habituation Programme')
                ->query(fn (Builder $query): Builder => $this->activeWindowQuery($query)
                    ->where('program_category', PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION)),
            PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE => Tab::make('Numeracy Excellence Programme')
                ->query(fn (Builder $query): Builder => $this->activeWindowQuery($query)
                    ->where('program_category', PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE)),
            PerpustakaanLiterasiMaterial::CATEGORY_SIGAP_29_KARAKTER => Tab::make('Sigap 29 Karakter')
                ->query(fn (Builder $query): Builder => $this->activeWindowQuery($query)
                    ->where('program_category', PerpustakaanLiterasiMaterial::CATEGORY_SIGAP_29_KARAKTER)),
            'uncategorized' => Tab::make('Belum Berkategori')
                ->query(fn (Builder $query): Builder => $this->activeWindowQuery($query)
                    ->where(function (Builder $inner): void {
                        $inner->whereNull('program_category')->orWhere('program_category', '');
                    })),
            'inactive' => Tab::make('Soal Non Aktif')
                ->query(fn (Builder $query): Builder => $this->inactiveWindowQuery($query)),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'active';
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
