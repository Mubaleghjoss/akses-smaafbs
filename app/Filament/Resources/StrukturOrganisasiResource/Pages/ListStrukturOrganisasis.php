<?php

namespace App\Filament\Resources\StrukturOrganisasiResource\Pages;

use App\Filament\Resources\StrukturOrganisasiResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListStrukturOrganisasis extends ListRecords
{
    protected static string $resource = StrukturOrganisasiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('panduanSejajar')
                ->label('Panduan Hirarki')
                ->icon('heroicon-o-information-circle')
                ->color('gray')
                ->modalHeading('Cara mengatur posisi sejajar')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->modalContent(new HtmlString('
                    <div class="space-y-3 text-sm leading-6 text-slate-600">
                        <p><strong>Posisi sejajar</strong> berarti beberapa jabatan berada di level yang sama.</p>
                        <p>Caranya: pilih <strong>Atasan Langsung</strong> yang sama pada semua posisi tersebut.</p>
                        <p>Gunakan aksi <strong>Ke Bawah</strong> untuk menjadikan item anak dari item di atasnya, dan <strong>Sejajar</strong> untuk mengembalikannya naik satu level.</p>
                        <p>Untuk urutan tampil antar posisi yang sejajar, edit langsung angka di kolom <strong>Urut</strong>.</p>
                        <p>Jika susunan di homepage perlu berbeda, atur <strong>Atasan Tampilan Homepage</strong>, <strong>Baris Tampilan</strong>, dan <strong>Urutan Tampilan</strong> dari form edit.</p>
                        <p>Jika <strong>Atasan Langsung</strong> dikosongkan, posisi itu akan menjadi level utama / root.</p>
                    </div>
                ')),
            Actions\CreateAction::make(),
        ];
    }
}
