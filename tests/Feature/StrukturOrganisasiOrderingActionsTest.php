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

    public function test_admin_can_indent_item_under_previous_sibling_from_the_table(): void
    {
        $admin = $this->createAdmin('admin-struktur-indent');

        $root = StrukturOrganisasi::query()->create([
            'jabatan' => 'Root A',
            'nama' => 'Ketua A',
            'foto' => 'struktur-organisasi/root-a.jpg',
            'urutan' => 1,
        ]);

        $wakilA1 = StrukturOrganisasi::query()->create([
            'parent_id' => $root->id,
            'jabatan' => 'Wakil A1',
            'nama' => 'Pengurus A1',
            'foto' => 'struktur-organisasi/wakil-a1.jpg',
            'urutan' => 1,
        ]);

        $wakilA2 = StrukturOrganisasi::query()->create([
            'parent_id' => $root->id,
            'jabatan' => 'Wakil A2',
            'nama' => 'Pengurus A2',
            'foto' => 'struktur-organisasi/wakil-a2.jpg',
            'urutan' => 2,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(ListStrukturOrganisasis::class)
            ->callTableAction('indent', $wakilA2)
            ->assertHasNoTableActionErrors();

        $this->assertSame(
            (int) $wakilA1->id,
            (int) $wakilA2->fresh()->parent_id,
            'Item yang di-indent harus menjadi anak dari item sejajar di atasnya.'
        );

        $this->assertSame(
            ['Wakil A1'],
            StrukturOrganisasi::query()->forParent($root->id)->ordered()->pluck('jabatan')->all()
        );

        $this->assertSame(
            [1],
            StrukturOrganisasi::query()->forParent($wakilA1->id)->ordered()->pluck('urutan')->all()
        );
    }

    public function test_admin_can_outdent_item_back_to_parent_level_from_the_table(): void
    {
        $admin = $this->createAdmin('admin-struktur-outdent');

        $root = StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/root-outdent.jpg',
            'urutan' => 1,
        ]);

        $wakil = StrukturOrganisasi::query()->create([
            'parent_id' => $root->id,
            'jabatan' => 'Wakil Kurikulum',
            'nama' => 'Ibu Kurikulum',
            'foto' => 'struktur-organisasi/wakil-outdent.jpg',
            'urutan' => 1,
        ]);

        $staf = StrukturOrganisasi::query()->create([
            'parent_id' => $wakil->id,
            'jabatan' => 'Staf Kurikulum',
            'nama' => 'Bapak Staf',
            'foto' => 'struktur-organisasi/staf-outdent.jpg',
            'urutan' => 1,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(ListStrukturOrganisasis::class)
            ->callTableAction('outdent', $staf)
            ->assertHasNoTableActionErrors();

        $this->assertSame(
            (int) $root->id,
            (int) $staf->fresh()->parent_id,
            'Item yang di-outdent harus sejajar dengan atasannya yang lama.'
        );

        $this->assertSame(
            ['Wakil Kurikulum', 'Staf Kurikulum'],
            StrukturOrganisasi::query()->forParent($root->id)->ordered()->pluck('jabatan')->all()
        );

        $this->assertSame(
            [1, 2],
            StrukturOrganisasi::query()->forParent($root->id)->ordered()->pluck('urutan')->all()
        );
    }

    public function test_move_sibling_up_does_not_affect_other_branch(): void
    {
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

        StrukturOrganisasi::query()->create([
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

        $this->assertTrue($wakilA2->canMoveUpWithinSiblings());
        $this->assertTrue($wakilA2->moveUpWithinSiblings());

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

    public function test_move_sibling_down_keeps_order_sequential(): void
    {
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

        $this->assertTrue($wakil1->canMoveDownWithinSiblings());
        $this->assertTrue($wakil1->moveDownWithinSiblings());

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
        $admin = $this->createAdmin('admin-struktur-parent-options');

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

        // Label opsi memakai format "jabatan - nama" (lihat
        // StrukturOrganisasiResource::structureOptionsForRecord). Diri sendiri
        // dan seluruh turunannya tidak boleh muncul sebagai calon atasan.
        Livewire::actingAs($admin)
            ->test(ListStrukturOrganisasis::class)
            ->assertTableSelectColumnHasOptions('parent_id', [
                $root->id => 'Kepala Sekolah - Ibu Kepala',
            ], $child);
    }

    protected function createAdmin(string $username): User
    {
        $admin = User::query()->create([
            'name' => 'Admin Struktur',
            'username' => $username,
            'password' => bcrypt('password'),
        ]);

        $admin->assignRole('admin');

        return $admin;
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

                // Kolom periode komite (migrasi 2026_04_05_110000). Wajib ikut
                // dibuat agar bentuk tabel sama dengan test lain yang memakai
                // tabel ini — perbedaan bentuk sempat menyebabkan kegagalan
                // bergantung urutan eksekusi.
                if (! Schema::hasColumn('struktur_organisasis', 'periode_tahun')) {
                    $table->unsignedSmallInteger('periode_tahun')->nullable();
                }

                if (! Schema::hasColumn('struktur_organisasis', 'periode_label')) {
                    $table->string('periode_label', 100)->nullable();
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
            // Kolom periode komite (migrasi 2026_04_05_110000) — lihat catatan
            // pada cabang Schema::table di atas.
            $table->unsignedSmallInteger('periode_tahun')->nullable();
            $table->string('periode_label', 100)->nullable();
            $table->timestamps();
        });
    }
}
