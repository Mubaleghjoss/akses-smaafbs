<?php

namespace Tests\Feature;

use App\Filament\Resources\StrukturOrganisasiResource\Pages\ListStrukturOrganisasis;
use App\Models\StrukturOrganisasi;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class StrukturOrganisasiOrderingActionsTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        $this->ensureStrukturOrganisasiTable();
    }

    public function test_admin_can_move_sibling_up_without_affecting_other_branch(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Struktur',
            'username' => 'admin-struktur-order-up',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $rootA = StrukturOrganisasi::query()->create([
            'jabatan' => 'Root A',
            'nama' => 'Ketua A',
            'foto' => 'struktur-organisasi/root-a.jpg',
            'urutan' => 1,
        ]);

        $rootB = StrukturOrganisasi::query()->create([
            'jabatan' => 'Root B',
            'nama' => 'Ketua B',
            'foto' => 'struktur-organisasi/root-b.jpg',
            'urutan' => 2,
        ]);

        $wakilA1 = StrukturOrganisasi::query()->create([
            'parent_id' => $rootA->id,
            'jabatan' => 'Wakil A1',
            'nama' => 'Pengurus A1',
            'foto' => 'struktur-organisasi/wakil-a1.jpg',
            'urutan' => 1,
        ]);

        $wakilA2 = StrukturOrganisasi::query()->create([
            'parent_id' => $rootA->id,
            'jabatan' => 'Wakil A2',
            'nama' => 'Pengurus A2',
            'foto' => 'struktur-organisasi/wakil-a2.jpg',
            'urutan' => 2,
        ]);

        StrukturOrganisasi::query()->create([
            'parent_id' => $rootB->id,
            'jabatan' => 'Wakil B1',
            'nama' => 'Pengurus B1',
            'foto' => 'struktur-organisasi/wakil-b1.jpg',
            'urutan' => 1,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(ListStrukturOrganisasis::class)
            ->callTableAction('move_up', $wakilA2)
            ->assertHasNoTableActionErrors();

        $this->assertSame([
            'Wakil A2',
            'Wakil A1',
        ], StrukturOrganisasi::query()->forParent($rootA->id)->ordered()->pluck('jabatan')->all());

        $this->assertSame(
            [1, 2],
            StrukturOrganisasi::query()->forParent($rootA->id)->ordered()->pluck('urutan')->all()
        );

        $this->assertSame(
            [1],
            StrukturOrganisasi::query()->forParent($rootB->id)->ordered()->pluck('urutan')->all()
        );
    }

    public function test_admin_can_move_sibling_down_and_order_remains_sequential(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Struktur',
            'username' => 'admin-struktur-order-down',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $root = StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/root.jpg',
            'urutan' => 1,
        ]);

        $wakil1 = StrukturOrganisasi::query()->create([
            'parent_id' => $root->id,
            'jabatan' => 'Wakil Kurikulum',
            'nama' => 'Ibu Kurikulum',
            'foto' => 'struktur-organisasi/wakil-1.jpg',
            'urutan' => 1,
        ]);

        StrukturOrganisasi::query()->create([
            'parent_id' => $root->id,
            'jabatan' => 'Wakil Kesiswaan',
            'nama' => 'Bapak Kesiswaan',
            'foto' => 'struktur-organisasi/wakil-2.jpg',
            'urutan' => 2,
        ]);

        StrukturOrganisasi::query()->create([
            'parent_id' => $root->id,
            'jabatan' => 'Wakil Sarpras',
            'nama' => 'Bapak Sarpras',
            'foto' => 'struktur-organisasi/wakil-3.jpg',
            'urutan' => 3,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(ListStrukturOrganisasis::class)
            ->callTableAction('move_down', $wakil1)
            ->assertHasNoTableActionErrors();

        $this->assertSame(
            ['Wakil Kesiswaan', 'Wakil Kurikulum', 'Wakil Sarpras'],
            StrukturOrganisasi::query()->forParent($root->id)->ordered()->pluck('jabatan')->all()
        );

        $this->assertSame(
            [1, 2, 3],
            StrukturOrganisasi::query()->forParent($root->id)->ordered()->pluck('urutan')->all()
        );
    }

    public function test_admin_can_see_direct_parent_options_from_the_table(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Struktur Parent Options',
            'username' => 'admin-struktur-parent-options',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $root = StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/root-parent-options.jpg',
            'urutan' => 1,
        ]);

        $child = StrukturOrganisasi::query()->create([
            'parent_id' => $root->id,
            'jabatan' => 'Wakil Kurikulum',
            'nama' => 'Ibu Kurikulum',
            'foto' => 'struktur-organisasi/child-parent-options.jpg',
            'urutan' => 1,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(ListStrukturOrganisasis::class)
            ->assertTableSelectColumnHasOptions('parent_id', [
                $root->id => 'Kepala Sekolah — Ibu Kepala',
            ], $child);
    }

    protected function ensureStrukturOrganisasiTable(): void
    {
        if (Schema::hasTable('struktur_organisasis')) {
            Schema::table('struktur_organisasis', function (Blueprint $table): void {
                if (! Schema::hasColumn('struktur_organisasis', 'jabatan')) {
                    $table->string('jabatan', 150);
                }

                if (! Schema::hasColumn('struktur_organisasis', 'nama')) {
                    $table->string('nama', 150);
                }

                if (! Schema::hasColumn('struktur_organisasis', 'foto')) {
                    $table->string('foto')->nullable();
                }

                if (! Schema::hasColumn('struktur_organisasis', 'parent_id')) {
                    $table->unsignedBigInteger('parent_id')->nullable();
                }

                if (! Schema::hasColumn('struktur_organisasis', 'guru_tendik_id')) {
                    $table->unsignedBigInteger('guru_tendik_id')->nullable();
                }

                if (! Schema::hasColumn('struktur_organisasis', 'homepage_parent_id')) {
                    $table->unsignedBigInteger('homepage_parent_id')->nullable();
                }

                if (! Schema::hasColumn('struktur_organisasis', 'urutan')) {
                    $table->unsignedInteger('urutan')->default(0);
                }

                if (! Schema::hasColumn('struktur_organisasis', 'homepage_row')) {
                    $table->unsignedInteger('homepage_row')->nullable();
                }

                if (! Schema::hasColumn('struktur_organisasis', 'homepage_order')) {
                    $table->unsignedInteger('homepage_order')->nullable();
                }

                if (! Schema::hasColumn('struktur_organisasis', 'kategori')) {
                    $table->string('kategori', 20)->default(StrukturOrganisasi::CATEGORY_SCHOOL);
                }

                if (! Schema::hasColumn('struktur_organisasis', 'created_at') || ! Schema::hasColumn('struktur_organisasis', 'updated_at')) {
                    $table->timestamps();
                }
            });

            return;
        }

        Schema::create('struktur_organisasis', function (Blueprint $table): void {
            $table->id();
            $table->string('jabatan', 150);
            $table->string('nama', 150);
            $table->string('foto')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('guru_tendik_id')->nullable();
            $table->unsignedBigInteger('homepage_parent_id')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->unsignedInteger('homepage_row')->nullable();
            $table->unsignedInteger('homepage_order')->nullable();
            $table->string('kategori', 20)->default(StrukturOrganisasi::CATEGORY_SCHOOL);
            $table->timestamps();
        });
    }
}
