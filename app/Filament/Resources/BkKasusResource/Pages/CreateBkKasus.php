<?php

namespace App\Filament\Resources\BkKasusResource\Pages;

use App\Filament\Resources\BkKasusResource;
use App\Models\BkKasus;
use App\Support\Bk\BkKasusSiswaSync;
use Filament\Resources\Pages\CreateRecord;

class CreateBkKasus extends CreateRecord
{
    protected static string $resource = BkKasusResource::class;

    /**
     * @var array<int, int>
     */
    protected array $siswaIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->siswaIds = collect($data['siswa_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        unset($data['siswa_ids']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var BkKasus $record */
        $record = $this->getRecord();

        BkKasusSiswaSync::sync($record, $this->siswaIds);
    }

    protected function getRedirectUrl(): string
    {
        return BkKasusResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
