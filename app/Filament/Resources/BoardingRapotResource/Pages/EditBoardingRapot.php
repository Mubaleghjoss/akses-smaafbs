<?php

namespace App\Filament\Resources\BoardingRapotResource\Pages;

use App\Filament\Resources\BoardingRapotResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoardingRapot extends EditRecord
{
    protected static string $resource = BoardingRapotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('backToList')
                ->label('Kembali ke daftar rapot')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => static::getResource()::getUrl('index')),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['konfirmasi_kelas_boarding_manual']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->syncFromSources();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
