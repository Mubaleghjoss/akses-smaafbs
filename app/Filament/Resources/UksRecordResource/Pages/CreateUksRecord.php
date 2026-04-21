<?php

namespace App\Filament\Resources\UksRecordResource\Pages;

use App\Filament\Resources\UksRecordResource;
use App\Support\Uks\UksAnthropometrySupport;
use Filament\Resources\Pages\CreateRecord;

class CreateUksRecord extends CreateRecord
{
    protected static string $resource = UksRecordResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['admin_id'] = auth()->id();

        if (UksAnthropometrySupport::hasStudentColumn()) {
            $data['siswa_id'] = UksAnthropometrySupport::resolveStudentId(
                $data['nama_siswa'] ?? null,
                $data['kelas'] ?? null,
            );
        }

        return $data;
    }
}
