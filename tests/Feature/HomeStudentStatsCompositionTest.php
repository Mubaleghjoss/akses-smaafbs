<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomeStudentStatsCompositionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureDataSiswaTable();
        $this->ensureBeritaTable();
        $this->ensurePrestasiTable();
    }

    public function test_student_stats_section_shows_prestasi_and_removes_total_siswa_card(): void
    {
        DB::table('data_siswa')->insert([
            [
                'nama' => 'Siswa Aktif',
                'nisn' => '10001',
                'status' => 'aktif',
                'tanggal_lahir' => '2010-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Siswa Alumni',
                'nisn' => '10002',
                'status' => 'alumni',
                'tanggal_lahir' => '2008-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $siswaId = (int) DB::table('data_siswa')->value('id');

        DB::table('prestasis')->insert([
            [
                'siswa_id' => $siswaId,
                'nama_lomba' => 'Lomba Sains Nasional',
                'tanggal_prestasi' => '2026-01-10',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'siswa_id' => $siswaId,
                'nama_lomba' => 'Lomba Bahasa Arab',
                'tanggal_prestasi' => '2026-02-12',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('Prestasi Siswa')
            ->assertDontSee('Total Seluruh Siswa')
            ->assertSee('Siswa Aktif')
            ->assertSee('Alumni');

        $this->assertMatchesRegularExpression(
            '/Prestasi Siswa<\/div>\s*<div class="mt-2 text-2xl font-semibold text-indigo-900">2<\/div>/s',
            $response->getContent()
        );
    }

    protected function ensureDataSiswaTable(): void
    {
        if (Schema::hasTable('data_siswa')) {
            return;
        }

        Schema::create('data_siswa', function (Blueprint $table): void {
            $table->id();
            $table->string('nama')->nullable();
            $table->string('nisn')->nullable();
            $table->string('status')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->timestamps();
        });
    }

    protected function ensureBeritaTable(): void
    {
        if (Schema::hasTable('berita')) {
            return;
        }

        Schema::create('berita', function (Blueprint $table): void {
            $table->id();
            $table->string('judul')->nullable();
            $table->text('konten')->nullable();
            $table->string('gambar')->nullable();
            $table->unsignedBigInteger('id_admin')->nullable();
            $table->string('status')->nullable();
            $table->date('tanggal_berita')->nullable();
            $table->timestamps();
        });
    }

    protected function ensurePrestasiTable(): void
    {
        if (Schema::hasTable('prestasis')) {
            return;
        }

        Schema::create('prestasis', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('siswa_id')->index();
            $table->string('nama_lomba', 150);
            $table->date('tanggal_prestasi')->nullable()->index();
            $table->string('penyelenggara', 150)->nullable();
            $table->string('juara', 100)->nullable();
            $table->string('hadiah', 255)->nullable();
            $table->text('keterangan')->nullable();
            $table->json('dokumentasi')->nullable();
            $table->json('sertifikat_files')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });
    }
}
