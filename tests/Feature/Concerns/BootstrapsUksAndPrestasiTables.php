<?php

namespace Tests\Feature\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait BootstrapsUksAndPrestasiTables
{
    protected function bootstrapUksAndPrestasiTables(): void
    {
        $this->createUksTable();
        $this->runPrestasiMigration();
        $this->runPrestasiKategoriMigration();
        $this->runUksMeasurementMigration();
    }

    protected function createUksTable(): void
    {
        if (Schema::hasTable('uks_records')) {
            return;
        }

        Schema::create('uks_records', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_siswa');
            $table->string('kelas')->nullable();
            $table->date('tanggal_sakit');
            $table->string('kategori')->nullable();
            $table->string('penanganan')->nullable();
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamps();
        });
    }

    protected function runPrestasiMigration(): void
    {
        $migration = require database_path('migrations/2026_03_26_171000_create_prestasi_tables.php');
        $migration->up();
    }

    protected function runPrestasiKategoriMigration(): void
    {
        $migration = require database_path('migrations/2026_04_27_110000_add_kategori_to_prestasis_table.php');
        $migration->up();
    }

    protected function runUksMeasurementMigration(): void
    {
        $migration = require database_path('migrations/2026_03_26_172000_add_measurements_to_uks_records_table.php');
        $migration->up();
    }
}
