<?php

namespace Tests;

use App\Filament\Resources\StrukturOrganisasiResource;
use App\Models\StrukturOrganisasi;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use ReflectionProperty;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        config(['server_sync.env_path' => storage_path('framework/testing/server-sync.env')]);
        File::deleteDirectory(storage_path('framework/testing/server-sync'));

        $this->resetSchemaAwareStaticCaches();
    }

    /**
     * Kosongkan cache statis yang menyimpan hasil Schema::hasColumn().
     *
     * MASALAH YANG DIPERBAIKI: StrukturOrganisasi menyimpan hasil pemeriksaan
     * kolom `periode_tahun` / `kategori` sekali lalu memakainya terus. Cache itu
     * bertahan antar test karena berada pada properti statis, sementara setiap
     * test membangun tabelnya sendiri dengan bentuk yang BERBEDA:
     *
     *   - StrukturKomiteResourceTest      -> tabel PUNYA periode_tahun
     *   - StrukturOrganisasiOrderingTest  -> tabel TANPA periode_tahun
     *
     * Bila test pertama berjalan lebih dulu, cache berisi `true`; test kedua
     * kemudian menulis `periode_tahun` ke tabel yang tidak memiliki kolom itu
     * dan gagal dengan "table struktur_organisasis has no column named
     * periode_tahun". Kegagalan karena itu BERGANTUNG URUTAN dan menjalar ke
     * berkas test lain yang berjalan sesudahnya.
     *
     * Reset dilakukan lewat Reflection dari sisi test, BUKAN dengan menambah
     * method publik pada model: kode produksi tidak perlu berubah hanya untuk
     * kepentingan pengujian.
     *
     * Alasan yang sama berlaku untuk cache statis pada
     * StrukturOrganisasiResource: `$scopedStructureRecordsCache` menyimpan
     * daftar record hasil query sekali per proses, sehingga opsi kolom Select
     * pada test berikutnya masih memuat baris milik test sebelumnya.
     */
    protected function resetSchemaAwareStaticCaches(): void
    {
        $modelProperties = [
            'periodColumnAvailableCache' => null,
            'categoryColumnAvailableCache' => null,
            'levelCache' => [],
        ];

        foreach ($modelProperties as $name => $nilaiAwal) {
            $this->resetStaticProperty(StrukturOrganisasi::class, $name, $nilaiAwal);
        }

        $resourceProperties = [
            'scopedStructureRecordsCache' => [],
            'scopedStructureDescendantMapCache' => [],
        ];

        foreach ($resourceProperties as $name => $nilaiAwal) {
            $this->resetStaticProperty(StrukturOrganisasiResource::class, $name, $nilaiAwal);
        }
    }

    protected function resetStaticProperty(string $class, string $name, mixed $nilaiAwal): void
    {
        if (! property_exists($class, $name)) {
            return;
        }

        $property = new ReflectionProperty($class, $name);
        $property->setAccessible(true);
        $property->setValue(null, $nilaiAwal);
    }
}
