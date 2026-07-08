<?php

namespace App\Filament\Resources\PerpustakaanLiterasiMaterialResource\Pages;

use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use App\Filament\Widgets\PerpustakaanLiterasiGlobalAnalytics;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiResponse;
use App\Support\Perpustakaan\LiterasiSimilarityAnalyzer;
use Filament\Actions;
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
                ->query(fn (Builder $query): Builder => $query->where('is_active', true)),
            PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION => Tab::make('Literacy Habituation Programme')
                ->query(fn (Builder $query): Builder => $query
                    ->where('is_active', true)
                    ->where('program_category', PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION)),
            PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE => Tab::make('Numeracy Excellence Programme')
                ->query(fn (Builder $query): Builder => $query
                    ->where('is_active', true)
                    ->where('program_category', PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE)),
            PerpustakaanLiterasiMaterial::CATEGORY_SIGAP_29_KARAKTER => Tab::make('Sigap 29 Karakter')
                ->query(fn (Builder $query): Builder => $query
                    ->where('is_active', true)
                    ->where('program_category', PerpustakaanLiterasiMaterial::CATEGORY_SIGAP_29_KARAKTER)),
            'uncategorized' => Tab::make('Belum Berkategori')
                ->query(fn (Builder $query): Builder => $query
                    ->where('is_active', true)
                    ->where(function (Builder $inner): void {
                        $inner->whereNull('program_category')->orWhere('program_category', '');
                    })),
            'inactive' => Tab::make('Soal Non Aktif')
                ->query(fn (Builder $query): Builder => $query->where('is_active', false)),
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
                    $analyzer = app(LiterasiSimilarityAnalyzer::class);
                    $total = 0;

                    PerpustakaanLiterasiResponse::query()
                        ->with('answers.question')
                        ->chunkById(50, function ($responses) use ($analyzer, &$total): void {
                            foreach ($responses as $response) {
                                $analyzer->analyzeResponse($response);
                                $total++;
                            }
                        });

                    Notification::make()
                        ->title('Hitung ulang plagiasi selesai')
                        ->body(number_format($total, 0, ',', '.').' responden dianalisa ulang.')
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
}
