<?php

namespace App\Filament\Resources\PerpustakaanLiterasiMaterialResource\Pages;

use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use App\Filament\Widgets\PerpustakaanLiterasiGlobalAnalytics;
use App\Models\PerpustakaanLiterasiResponse;
use App\Support\Perpustakaan\LiterasiSimilarityAnalyzer;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPerpustakaanLiterasiMaterials extends ListRecords
{
    protected static string $resource = PerpustakaanLiterasiMaterialResource::class;

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
            Actions\CreateAction::make(),
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
}
