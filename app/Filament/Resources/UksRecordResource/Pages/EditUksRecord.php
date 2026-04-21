<?php

namespace App\Filament\Resources\UksRecordResource\Pages;

use App\Filament\Resources\UksRecordResource;
use App\Support\Uks\UksAnthropometrySupport;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUksRecord extends EditRecord
{
    protected static string $resource = UksRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (UksAnthropometrySupport::hasStudentColumn()) {
            $data['siswa_id'] = UksAnthropometrySupport::resolveStudentId(
                $data['nama_siswa'] ?? null,
                $data['kelas'] ?? null,
            );
        }

        return $data;
    }
}
