<?php

namespace Tests\Feature;

use App\Filament\Resources\StrukturKomiteResource\Pages\CreateStrukturKomite;
use App\Models\StrukturOrganisasi;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class StrukturKomiteResourceTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        $this->ensureStrukturOrganisasiTable();
    }

    public function test_admin_can_create_committee_structure_without_photo(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Struktur Komite',
            'username' => 'admin-struktur-komite',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(CreateStrukturKomite::class)
            ->set('data.periode_tahun', 2026)
            ->set('data.periode_label', '2026-2027')
            ->set('data.jabatan', 'Ketua Komite')
            ->set('data.nama', 'Ibu Ketua Komite')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('struktur_organisasis', [
            'kategori' => StrukturOrganisasi::CATEGORY_COMMITTEE,
            'periode_tahun' => 2026,
            'periode_label' => '2026-2027',
            'jabatan' => 'Ketua Komite',
            'nama' => 'Ibu Ketua Komite',
            'foto' => null,
        ]);
    }

    protected function ensureStrukturOrganisasiTable(): void
    {
        if (Schema::hasTable('struktur_organisasis')) {
            return;
        }

        Schema::create('struktur_organisasis', function (Blueprint $table): void {
            $table->id();
            $table->string('jabatan', 150);
            $table->string('nama', 150);
            $table->string('foto')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('homepage_parent_id')->nullable();
            $table->unsignedBigInteger('guru_tendik_id')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->unsignedInteger('homepage_row')->nullable();
            $table->unsignedInteger('homepage_order')->nullable();
            $table->string('kategori', 20)->default(StrukturOrganisasi::CATEGORY_SCHOOL);
            $table->unsignedSmallInteger('periode_tahun')->nullable();
            $table->string('periode_label', 100)->nullable();
            $table->timestamps();
        });
    }
}
