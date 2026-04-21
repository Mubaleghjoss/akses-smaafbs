<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('data_siswa')) {
            Schema::create('data_siswa', function (Blueprint $table): void {
                $table->id();
            });
        }

        if (! Schema::hasTable('berita')) {
            Schema::create('berita', function (Blueprint $table): void {
                $table->id();
                $table->string('status')->nullable();
                $table->date('tanggal_berita')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('perpustakaan_buku')) {
            Schema::create('perpustakaan_buku', function (Blueprint $table): void {
                $table->id();
            });
        }
    }

    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
