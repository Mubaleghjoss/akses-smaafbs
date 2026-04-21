<?php

namespace App\Exports;

use App\Models\User;
use App\Support\DataSiswa\DataSiswaSupport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class DataSiswaProfileExport implements FromArray, ShouldAutoSize
{
    public function __construct(
        protected ?User $user = null,
    ) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return DataSiswaSupport::simpleProfileExportRows($this->user);
    }
}
