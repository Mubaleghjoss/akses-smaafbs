<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PwaInstallPromptTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createPublicHomepageTables();
        $this->createUsersTable();
    }

    public function test_public_layout_renders_hidden_install_cta_hook_for_supported_browsers(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-pwa-install-root class="hidden"', false)
            ->assertSee('data-pwa-install-trigger', false)
            ->assertSee('Install App');
    }

    public function test_admin_login_renders_install_cta_hook_without_disrupting_login_flow(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('data-pwa-install-root', false)
            ->assertSee('data-pwa-install-trigger', false)
            ->assertSee('Install App')
            ->assertSee('Login sidik jari / passkey');
    }

    protected function createPublicHomepageTables(): void
    {
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

    protected function createUsersTable(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }
}
