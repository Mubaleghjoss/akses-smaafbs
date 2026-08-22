<?php

namespace App\Console\Commands;

use App\Models\DataSiswa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillDataSiswaBillingCodes extends Command
{
    protected $signature = 'data-siswa:backfill-billing-codes
        {--dry-run : Hitung data yang akan diisi tanpa mengubah database}';

    protected $description = 'Isi billing code unik untuk siswa yang belum memilikinya tanpa mengubah kode lama.';

    public function handle(): int
    {
        $table = (new DataSiswa)->getTable();

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'billing_code')) {
            $this->error('Tabel data_siswa atau kolom billing_code tidak tersedia.');

            return self::FAILURE;
        }

        $query = DataSiswa::query()
            ->where(fn ($builder) => $builder
                ->whereNull('billing_code')
                ->orWhereRaw("TRIM(COALESCE(billing_code, '')) = ''"));

        $pending = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("{$pending} siswa membutuhkan billing code.");

            return self::SUCCESS;
        }

        $updated = 0;

        $query->orderBy('id')->chunkById(100, function ($students) use (&$updated): void {
            /** @var DataSiswa $student */
            foreach ($students as $student) {
                if (trim((string) $student->billing_code) !== '') {
                    continue;
                }

                $student->forceFill([
                    'billing_code' => DataSiswa::generateUniqueBillingCode(),
                ])->saveQuietly();
                $updated++;
            }
        });

        $this->info("{$updated} billing code siswa berhasil diisi.");

        return self::SUCCESS;
    }
}
