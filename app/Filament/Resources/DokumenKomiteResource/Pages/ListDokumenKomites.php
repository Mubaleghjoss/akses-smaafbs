<?php

namespace App\Filament\Resources\DokumenKomiteResource\Pages;

use App\Filament\Resources\DokumenKomiteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDokumenKomites extends ListRecords
{
    protected static string $resource = DokumenKomiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Dokumen Komite'),
        ];
    }
}
