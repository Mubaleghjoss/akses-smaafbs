<?php

namespace Tests\Feature;

use App\Models\StrukturOrganisasi;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class StrukturOrganisasiModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureStrukturOrganisasiTable();
    }

    public function test_for_homepage_scope_returns_top_down_order(): void
    {
        StrukturOrganisasi::query()->create([
            'jabatan' => 'Wakil Kepala Sekolah',
            'nama' => 'Bapak Wakil',
            'foto' => 'struktur-organisasi/wakil.jpg',
            'kategori' => StrukturOrganisasi::CATEGORY_SCHOOL,
            'urutan' => 2,
        ]);

        StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/kepala.jpg',
            'kategori' => StrukturOrganisasi::CATEGORY_SCHOOL,
            'urutan' => 1,
        ]);

        $orderedJabatan = StrukturOrganisasi::query()
            ->forHomepage()
            ->pluck('jabatan')
            ->all();

        $this->assertSame([
            'Kepala Sekolah',
            'Wakil Kepala Sekolah',
        ], $orderedJabatan);
    }

    public function test_root_ordering_and_homepage_queries_are_isolated_per_category(): void
    {
        StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/kepala.jpg',
            'kategori' => StrukturOrganisasi::CATEGORY_SCHOOL,
            'urutan' => 1,
        ]);

        StrukturOrganisasi::query()->create([
            'jabatan' => 'Ketua Komite',
            'nama' => 'Bapak Komite',
            'foto' => 'struktur-komite/ketua.jpg',
            'kategori' => StrukturOrganisasi::CATEGORY_COMMITTEE,
            'urutan' => 1,
        ]);

        $schoolSecondRoot = StrukturOrganisasi::query()->create([
            'jabatan' => 'Wakil Kepala Sekolah',
            'nama' => 'Bapak Wakil',
            'foto' => 'struktur-organisasi/wakil.jpg',
            'kategori' => StrukturOrganisasi::CATEGORY_SCHOOL,
        ]);

        $committeeSecondRoot = StrukturOrganisasi::query()->create([
            'jabatan' => 'Sekretaris Komite',
            'nama' => 'Ibu Sekretaris',
            'foto' => 'struktur-komite/sekretaris.jpg',
            'kategori' => StrukturOrganisasi::CATEGORY_COMMITTEE,
        ]);

        $this->assertSame(2, $schoolSecondRoot->urutan);
        $this->assertSame(2, $committeeSecondRoot->urutan);

        $this->assertSame([
            'Kepala Sekolah',
            'Wakil Kepala Sekolah',
        ], StrukturOrganisasi::query()->forHomepage(StrukturOrganisasi::CATEGORY_SCHOOL)->pluck('jabatan')->all());

        $this->assertSame([
            'Ketua Komite',
            'Sekretaris Komite',
        ], StrukturOrganisasi::query()->forHomepage(StrukturOrganisasi::CATEGORY_COMMITTEE)->pluck('jabatan')->all());
    }

    public function test_parent_must_belong_to_same_structure_category_when_category_column_exists(): void
    {
        $schoolRoot = StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/kepala.jpg',
            'kategori' => StrukturOrganisasi::CATEGORY_SCHOOL,
            'urutan' => 1,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('kategori struktur yang sama');

        StrukturOrganisasi::query()->create([
            'parent_id' => $schoolRoot->id,
            'jabatan' => 'Sekretaris Komite',
            'nama' => 'Ibu Sekretaris',
            'foto' => 'struktur-komite/sekretaris.jpg',
            'kategori' => StrukturOrganisasi::CATEGORY_COMMITTEE,
            'urutan' => 1,
        ]);
    }

    public function test_committee_parent_must_belong_to_same_period(): void
    {
        $committeeRoot = StrukturOrganisasi::query()->create([
            'jabatan' => 'Ketua Komite 2024',
            'nama' => 'Bapak Komite Lama',
            'kategori' => StrukturOrganisasi::CATEGORY_COMMITTEE,
            'periode_tahun' => 2024,
            'periode_label' => '2024-2025',
            'urutan' => 1,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('periode yang sama');

        StrukturOrganisasi::query()->create([
            'parent_id' => $committeeRoot->id,
            'jabatan' => 'Sekretaris Komite 2026',
            'nama' => 'Ibu Sekretaris Baru',
            'kategori' => StrukturOrganisasi::CATEGORY_COMMITTEE,
            'periode_tahun' => 2026,
            'periode_label' => '2026-2027',
            'urutan' => 1,
        ]);
    }

    public function test_committee_homepage_tree_is_scoped_per_period_and_defaults_missing_period(): void
    {
        $defaultPeriodRoot = StrukturOrganisasi::query()->create([
            'jabatan' => 'Ketua Komite Aktif',
            'nama' => 'Bapak Komite Aktif',
            'kategori' => StrukturOrganisasi::CATEGORY_COMMITTEE,
            'urutan' => 1,
        ]);

        $archivedRoot = StrukturOrganisasi::query()->create([
            'jabatan' => 'Ketua Komite Lama',
            'nama' => 'Ibu Komite Lama',
            'kategori' => StrukturOrganisasi::CATEGORY_COMMITTEE,
            'periode_tahun' => 2024,
            'periode_label' => '2024-2025',
            'urutan' => 1,
        ]);

        StrukturOrganisasi::query()->create([
            'parent_id' => $defaultPeriodRoot->id,
            'jabatan' => 'Sekretaris Komite Aktif',
            'nama' => 'Ibu Sekretaris Aktif',
            'kategori' => StrukturOrganisasi::CATEGORY_COMMITTEE,
            'periode_tahun' => (int) now()->format('Y'),
            'urutan' => 1,
        ]);

        $this->assertSame((int) now()->format('Y'), $defaultPeriodRoot->fresh()->periode_tahun);

        $activeTree = StrukturOrganisasi::homepageTree(
            StrukturOrganisasi::CATEGORY_COMMITTEE,
            (int) now()->format('Y'),
        );
        $archivedTree = StrukturOrganisasi::homepageTree(
            StrukturOrganisasi::CATEGORY_COMMITTEE,
            2024,
        );

        $this->assertCount(1, $activeTree);
        $this->assertSame('Ketua Komite Aktif', $activeTree->first()->jabatan);
        $this->assertSame(
            ['Sekretaris Komite Aktif'],
            collect($activeTree->first()->children ?? [])->pluck('jabatan')->all(),
        );

        $this->assertCount(1, $archivedTree);
        $this->assertSame($archivedRoot->jabatan, $archivedTree->first()->jabatan);
        $this->assertCount(0, collect($archivedTree->first()->children ?? []));
    }

    public function test_children_are_ordered_within_same_parent(): void
    {
        $parent = StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/kepala.jpg',
            'urutan' => 1,
        ]);

        StrukturOrganisasi::query()->create([
            'parent_id' => $parent->id,
            'jabatan' => 'Wakil Kesiswaan',
            'nama' => 'Bapak Kesiswaan',
            'foto' => 'struktur-organisasi/kesiswaan.jpg',
            'urutan' => 2,
        ]);

        StrukturOrganisasi::query()->create([
            'parent_id' => $parent->id,
            'jabatan' => 'Wakil Kurikulum',
            'nama' => 'Ibu Kurikulum',
            'foto' => 'struktur-organisasi/kurikulum.jpg',
            'urutan' => 1,
        ]);

        $this->assertSame([
            'Wakil Kurikulum',
            'Wakil Kesiswaan',
        ], $parent->children()->pluck('jabatan')->all());
    }

    public function test_urutan_is_auto_assigned_when_empty(): void
    {
        StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/kepala.jpg',
            'urutan' => 1,
        ]);

        $record = StrukturOrganisasi::query()->create([
            'jabatan' => 'Wakil Kepala Sekolah',
            'nama' => 'Bapak Wakil',
            'foto' => 'struktur-organisasi/wakil.jpg',
        ]);

        $this->assertSame(2, $record->urutan);
    }

    public function test_urutan_auto_assignment_is_scoped_to_parent_branch(): void
    {
        $rootA = StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/kepala.jpg',
            'urutan' => 1,
        ]);

        $rootB = StrukturOrganisasi::query()->create([
            'jabatan' => 'Komite Sekolah',
            'nama' => 'Bapak Komite',
            'foto' => 'struktur-organisasi/komite.jpg',
            'urutan' => 2,
        ]);

        StrukturOrganisasi::query()->create([
            'parent_id' => $rootA->id,
            'jabatan' => 'Wakil Kurikulum',
            'nama' => 'Ibu Kurikulum',
            'foto' => 'struktur-organisasi/kurikulum.jpg',
            'urutan' => 1,
        ]);

        $branchChild = StrukturOrganisasi::query()->create([
            'parent_id' => $rootA->id,
            'jabatan' => 'Wakil Kesiswaan',
            'nama' => 'Bapak Kesiswaan',
            'foto' => 'struktur-organisasi/kesiswaan.jpg',
        ]);

        $otherRootChild = StrukturOrganisasi::query()->create([
            'parent_id' => $rootB->id,
            'jabatan' => 'Sekretaris',
            'nama' => 'Ibu Sekretaris',
            'foto' => 'struktur-organisasi/sekretaris.jpg',
        ]);

        $this->assertSame(2, $branchChild->urutan);
        $this->assertSame(1, $otherRootChild->urutan);
    }

    public function test_sibling_order_is_resequenced_after_parent_change(): void
    {
        $rootA = StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/kepala.jpg',
            'urutan' => 1,
        ]);

        $rootB = StrukturOrganisasi::query()->create([
            'jabatan' => 'Komite Sekolah',
            'nama' => 'Bapak Komite',
            'foto' => 'struktur-organisasi/komite.jpg',
            'urutan' => 2,
        ]);

        StrukturOrganisasi::query()->create([
            'parent_id' => $rootA->id,
            'jabatan' => 'Wakil Kurikulum',
            'nama' => 'Ibu Kurikulum',
            'foto' => 'struktur-organisasi/kurikulum.jpg',
            'urutan' => 1,
        ]);

        $movingChild = StrukturOrganisasi::query()->create([
            'parent_id' => $rootA->id,
            'jabatan' => 'Wakil Kesiswaan',
            'nama' => 'Bapak Kesiswaan',
            'foto' => 'struktur-organisasi/kesiswaan.jpg',
            'urutan' => 2,
        ]);

        StrukturOrganisasi::query()->create([
            'parent_id' => $rootB->id,
            'jabatan' => 'Sekretaris',
            'nama' => 'Ibu Sekretaris',
            'foto' => 'struktur-organisasi/sekretaris.jpg',
            'urutan' => 1,
        ]);

        $movingChild->update([
            'parent_id' => $rootB->id,
            'urutan' => 99,
        ]);

        $this->assertSame(
            [1],
            StrukturOrganisasi::query()->forParent($rootA->id)->ordered()->pluck('urutan')->all()
        );

        $this->assertSame(
            [1, 2],
            StrukturOrganisasi::query()->forParent($rootB->id)->ordered()->pluck('urutan')->all()
        );
    }

    public function test_sibling_order_is_resequenced_after_leaf_deletion(): void
    {
        $root = StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/kepala.jpg',
            'urutan' => 1,
        ]);

        StrukturOrganisasi::query()->create([
            'parent_id' => $root->id,
            'jabatan' => 'Wakil Kurikulum',
            'nama' => 'Ibu Kurikulum',
            'foto' => 'struktur-organisasi/kurikulum.jpg',
            'urutan' => 1,
        ]);

        $toDelete = StrukturOrganisasi::query()->create([
            'parent_id' => $root->id,
            'jabatan' => 'Wakil Kesiswaan',
            'nama' => 'Bapak Kesiswaan',
            'foto' => 'struktur-organisasi/kesiswaan.jpg',
            'urutan' => 2,
        ]);

        StrukturOrganisasi::query()->create([
            'parent_id' => $root->id,
            'jabatan' => 'Wakil Sarpras',
            'nama' => 'Bapak Sarpras',
            'foto' => 'struktur-organisasi/sarpras.jpg',
            'urutan' => 3,
        ]);

        $toDelete->delete();

        $this->assertSame(
            [1, 2],
            StrukturOrganisasi::query()->forParent($root->id)->ordered()->pluck('urutan')->all()
        );
    }

    public function test_self_parent_is_rejected(): void
    {
        $record = StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/kepala.jpg',
            'urutan' => 1,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('tidak boleh menjadi atasan dirinya sendiri');

        $record->update([
            'parent_id' => $record->id,
        ]);
    }

    public function test_descendant_cycle_is_rejected(): void
    {
        $root = StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/kepala.jpg',
            'urutan' => 1,
        ]);

        $child = StrukturOrganisasi::query()->create([
            'parent_id' => $root->id,
            'jabatan' => 'Wakil Kurikulum',
            'nama' => 'Ibu Kurikulum',
            'foto' => 'struktur-organisasi/kurikulum.jpg',
            'urutan' => 1,
        ]);

        $grandChild = StrukturOrganisasi::query()->create([
            'parent_id' => $child->id,
            'jabatan' => 'Guru Matematika',
            'nama' => 'Bapak Matematika',
            'foto' => 'struktur-organisasi/matematika.jpg',
            'urutan' => 1,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('tidak boleh dipindahkan ke cabang turunannya sendiri');

        $root->update([
            'parent_id' => $grandChild->id,
        ]);
    }

    public function test_parent_with_children_cannot_be_deleted(): void
    {
        $root = StrukturOrganisasi::query()->create([
            'jabatan' => 'Kepala Sekolah',
            'nama' => 'Ibu Kepala',
            'foto' => 'struktur-organisasi/kepala.jpg',
            'urutan' => 1,
        ]);

        StrukturOrganisasi::query()->create([
            'parent_id' => $root->id,
            'jabatan' => 'Wakil Kurikulum',
            'nama' => 'Ibu Kurikulum',
            'foto' => 'struktur-organisasi/kurikulum.jpg',
            'urutan' => 1,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('masih memiliki cabang');

        $root->delete();
    }

    public function test_nonexistent_parent_is_rejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Atasan langsung yang dipilih tidak ditemukan');

        StrukturOrganisasi::query()->create([
            'parent_id' => 99999,
            'jabatan' => 'Guru Matematika',
            'nama' => 'Bapak Matematika',
            'foto' => 'struktur-organisasi/matematika.jpg',
            'urutan' => 1,
        ]);
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
            $table->unsignedSmallInteger('periode_tahun')->nullable();
            $table->string('periode_label', 100)->nullable();
            $table->timestamps();
        });
    }
}
