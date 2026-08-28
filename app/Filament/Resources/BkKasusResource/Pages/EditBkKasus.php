<?php

namespace App\Filament\Resources\BkKasusResource\Pages;

use App\Filament\Resources\BkKasusResource;
use App\Models\BkKasus;
use App\Support\Bk\BkKasusSiswaSync;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBkKasus extends EditRecord
{
    protected static string $resource = BkKasusResource::class;

    /**
     * @var array<int, int>
     */
    protected array $siswaIds = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var BkKasus $record */
        $record = $this->getRecord();

        $data['siswa_ids'] = $record->siswa()->pluck('data_siswa.id')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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

    protected function afterSave(): void
    {
        /** @var BkKasus $record */
        $record = $this->getRecord();

        BkKasusSiswaSync::sync($record, $this->siswaIds);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Hapus laporan SIGAP?')
                ->modalDescription('Laporan beserta daftar siswa yang terhubung akan dihapus permanen.')
                ->modalSubmitActionLabel('Ya, hapus laporan'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return BkKasusResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
