<?php

namespace App\Filament\Concerns;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;

trait HasConfirmedDeleteActions
{
    protected static function makeDeleteTableAction(string $recordLabel = 'data'): DeleteAction
    {
        return DeleteAction::make()
            ->requiresConfirmation()
            ->modalHeading("Hapus {$recordLabel}?")
            ->modalDescription('Tindakan ini tidak bisa dibatalkan. Pastikan data yang dipilih memang ingin dihapus.')
            ->modalSubmitActionLabel('Ya, hapus data')
            ->successNotificationTitle(ucfirst($recordLabel).' berhasil dihapus.');
    }

    protected static function makeDeleteBulkTableAction(): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->label('Hapus Terpilih')
            ->requiresConfirmation()
            ->modalHeading('Hapus data terpilih?')
            ->modalDescription('Semua data yang dipilih akan dihapus permanen. Periksa kembali sebelum melanjutkan.')
            ->modalSubmitActionLabel('Ya, hapus data terpilih')
            ->successNotificationTitle('Data terpilih berhasil dihapus.');
    }
}
