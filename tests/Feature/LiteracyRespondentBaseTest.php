<?php

namespace Tests\Feature;

use App\Models\DataSiswa;
use App\Models\PerpustakaanLiterasiDispensation;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiResponse;
use App\Support\Perpustakaan\LiteracyRespondentBase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\BootstrapsAdminFeatureTables;
use Tests\TestCase;

/**
 * Menegakkan aturan inti: siswa berstatus izin / sakit / tes MT TIDAK dihitung
 * sebagai pengisi maupun sebagai bagian dari basis responden.
 */
class LiteracyRespondentBaseTest extends TestCase
{
    use BootstrapsAdminFeatureTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapAdminFeatureTables();
        $this->runLiteracyTables();
    }

    public function test_dispensasi_dikeluarkan_dari_basis_responden_dan_bukan_pengisi(): void
    {
        $material = $this->createMaterial('Materi Numerasi');

        $mengisi = $this->createStudent('Ahmad', 'X 1');
        $izin = $this->createStudent('Budi', 'X 1');
        $sakit = $this->createStudent('Cici', 'X 1');
        $tesMt = $this->createStudent('Dedi', 'X 1');
        $belum = $this->createStudent('Eka', 'X 1');

        $this->createResponse($material, $mengisi);
        $this->createDispensation($material, $izin, PerpustakaanLiterasiDispensation::REASON_PERMISSION);
        $this->createDispensation($material, $sakit, PerpustakaanLiterasiDispensation::REASON_SICK);
        $this->createDispensation($material, $tesMt, PerpustakaanLiterasiDispensation::REASON_MT_TEST);

        $base = LiteracyRespondentBase::forMaterial($material);

        // 5 siswa aktif, 3 dispensasi -> basis hanya 2 orang.
        $this->assertSame(5, $base['active_total']);
        $this->assertSame(3, $base['excluded_total']);
        $this->assertSame(2, $base['respondent_base']);
        $this->assertSame(1, $base['completed_total']);
        $this->assertSame(1, $base['missing_total']);
        $this->assertSame(50.0, $base['participation_percentage']);
        $this->assertSame('1/2', $base['ratio']);

        $this->assertSame([
            PerpustakaanLiterasiDispensation::REASON_PERMISSION => 1,
            PerpustakaanLiterasiDispensation::REASON_SICK => 1,
            PerpustakaanLiterasiDispensation::REASON_MT_TEST => 1,
        ], $base['excluded_by_reason']);

        $missingIds = collect($base['classes'][0]['missing_students'])->pluck('student_id')->all();
        $this->assertSame([$belum->getKey()], $missingIds);
    }

    public function test_semua_sisa_mengisi_menghasilkan_seratus_persen_walau_ada_dispensasi(): void
    {
        $material = $this->createMaterial('Materi Penuh');

        foreach (['Satu', 'Dua', 'Tiga'] as $name) {
            $this->createResponse($material, $this->createStudent($name, 'XI 1'));
        }

        $this->createDispensation(
            $material,
            $this->createStudent('Empat', 'XI 1'),
            PerpustakaanLiterasiDispensation::REASON_MT_TEST,
        );

        $base = LiteracyRespondentBase::forMaterial($material);

        $this->assertSame(3, $base['respondent_base']);
        $this->assertSame(3, $base['completed_total']);
        $this->assertSame(0, $base['missing_total']);
        $this->assertSame(100.0, $base['participation_percentage']);
    }

    public function test_siswa_yang_akhirnya_mengisi_tetap_dihitung_sebagai_pengisi(): void
    {
        $material = $this->createMaterial('Materi Susulan');
        $student = $this->createStudent('Fajar', 'X 2');

        $this->createDispensation($material, $student, PerpustakaanLiterasiDispensation::REASON_SICK);
        $this->createResponse($material, $student);

        $base = LiteracyRespondentBase::forMaterial($material);

        $this->assertSame(1, $base['respondent_base']);
        $this->assertSame(1, $base['completed_total']);
        $this->assertSame(0, $base['excluded_total']);
        $this->assertSame(100.0, $base['participation_percentage']);
    }

    public function test_persentase_tidak_melebihi_seratus_untuk_banyak_materi(): void
    {
        $pertama = $this->createMaterial('Materi Satu');
        $kedua = $this->createMaterial('Materi Dua');
        $ketiga = $this->createMaterial('Materi Tiga');

        $rajin = $this->createStudent('Gilang', 'XII 1');
        $malas = $this->createStudent('Hana', 'XII 1');

        foreach ([$pertama, $kedua, $ketiga] as $material) {
            $this->createResponse($material, $rajin);
        }

        $this->createResponse($pertama, $malas);

        $base = LiteracyRespondentBase::forMaterialIds([
            $pertama->getKey(),
            $kedua->getKey(),
            $ketiga->getKey(),
        ]);

        // 2 siswa x 3 materi = 6 slot; terisi 4.
        $this->assertSame(3, $base['material_count']);
        $this->assertSame(6, $base['respondent_base']);
        $this->assertSame(4, $base['completed_total']);
        $this->assertSame(2, $base['missing_total']);
        $this->assertSame(66.7, $base['participation_percentage']);
        $this->assertLessThanOrEqual(100.0, $base['participation_percentage']);
    }

    public function test_jawaban_di_sampah_tidak_dihitung_sebagai_pengisi(): void
    {
        $material = $this->createMaterial('Materi Sampah');
        $student = $this->createStudent('Indra', 'X 1');

        $response = $this->createResponse($material, $student);
        $response->delete();

        $base = LiteracyRespondentBase::forMaterial($material);

        $this->assertSame(1, $base['respondent_base']);
        $this->assertSame(0, $base['completed_total']);
        $this->assertSame(1, $base['trashed_total']);
        $this->assertSame(0.0, $base['participation_percentage']);
    }

    public function test_hanya_rombel_aktif_yang_masuk_perhitungan(): void
    {
        $material = $this->createMaterial('Materi Rombel');

        DB::table('rombels')->insert([
            ['nama' => 'X 1', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nama' => 'ALUMNI 2021/2022', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->createResponse($material, $this->createStudent('Joko', 'X 1'));
        $this->createStudent('Kartika', 'ALUMNI 2021/2022');

        $base = LiteracyRespondentBase::forMaterial($material);

        $this->assertSame(1, $base['respondent_base']);
        $this->assertSame(1, $base['completed_total']);
        $this->assertCount(1, $base['classes']);
        $this->assertSame('X 1', $base['classes'][0]['class']);
    }

    public function test_siswa_non_aktif_tidak_masuk_perhitungan(): void
    {
        $material = $this->createMaterial('Materi Non Aktif');

        $this->createResponse($material, $this->createStudent('Lina', 'X 1'));
        $this->createStudent('Mail', 'X 1', ['status' => 'mutasi']);

        $base = LiteracyRespondentBase::forMaterial($material);

        $this->assertSame(1, $base['active_total']);
        $this->assertSame(1, $base['respondent_base']);
    }

    public function test_filter_kelas_membatasi_hasil(): void
    {
        $material = $this->createMaterial('Materi Filter');

        $this->createResponse($material, $this->createStudent('Nadia', 'X 1'));
        $this->createStudent('Omar', 'XI 1');

        $base = LiteracyRespondentBase::forMaterial($material, ['X 1']);

        $this->assertSame(1, $base['respondent_base']);
        $this->assertCount(1, $base['classes']);
        $this->assertSame('X 1', $base['classes'][0]['class']);
    }

    public function test_materi_dalam_lingkup_mengikuti_kategori_dan_rentang_tanggal(): void
    {
        $numerasi = $this->createMaterial('Numerasi Agustus', [
            'program_category' => PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE,
            'opens_at' => '2026-08-10',
        ]);
        $literasi = $this->createMaterial('Literasi Agustus', [
            'program_category' => PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION,
            'opens_at' => '2026-08-11',
        ]);
        $luarRentang = $this->createMaterial('Numerasi Juli', [
            'program_category' => PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE,
            'opens_at' => '2026-07-01',
        ]);

        $ids = LiteracyRespondentBase::materialIdsInScope(
            PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE,
            now()->parse('2026-08-01')->startOfDay(),
            now()->parse('2026-08-31')->endOfDay(),
        );

        $this->assertContains($numerasi->getKey(), $ids);
        $this->assertNotContains($literasi->getKey(), $ids);
        $this->assertNotContains($luarRentang->getKey(), $ids);
    }

    public function test_hasil_kosong_ketika_tidak_ada_materi(): void
    {
        $base = LiteracyRespondentBase::forMaterialIds([]);

        $this->assertSame(0, $base['respondent_base']);
        $this->assertNull($base['participation_percentage']);
        $this->assertSame([], $base['classes']);
    }

    protected function createMaterial(string $title, array $attributes = []): PerpustakaanLiterasiMaterial
    {
        return PerpustakaanLiterasiMaterial::query()->create(array_merge([
            'title' => $title,
            'reading_content' => 'Bacaan untuk pengujian basis responden.',
            'program_category' => PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE,
            'is_active' => true,
        ], $attributes));
    }

    protected function createStudent(string $name, string $class, array $attributes = []): DataSiswa
    {
        return DataSiswa::query()->create(array_merge([
            'nama' => $name,
            'rombel_saat_ini' => $class,
            'status' => 'aktif',
        ], $attributes));
    }

    protected function createResponse(
        PerpustakaanLiterasiMaterial $material,
        DataSiswa $student,
    ): PerpustakaanLiterasiResponse {
        return PerpustakaanLiterasiResponse::query()->create([
            'material_id' => $material->getKey(),
            'data_siswa_id' => $student->getKey(),
            'student_name_snapshot' => $student->nama,
            'student_class_snapshot' => $student->rombel_saat_ini,
            'submitted_at' => now(),
        ]);
    }

    protected function createDispensation(
        PerpustakaanLiterasiMaterial $material,
        DataSiswa $student,
        string $reason,
    ): PerpustakaanLiterasiDispensation {
        return PerpustakaanLiterasiDispensation::query()->create([
            'material_id' => $material->getKey(),
            'data_siswa_id' => $student->getKey(),
            'reason' => $reason,
            'student_name_snapshot' => $student->nama,
            'student_class_snapshot' => $student->rombel_saat_ini,
            'confirmed_at' => now(),
            'note' => $reason === PerpustakaanLiterasiDispensation::REASON_PERMISSION
                ? 'Izin acara keluarga di luar kota.'
                : null,
        ]);
    }

    protected function runLiteracyTables(): void
    {
        foreach ([
            '2026_05_11_120000_create_perpustakaan_literasi_program_tables.php',
            '2026_05_12_090000_add_grading_to_perpustakaan_literasi_answers_table.php',
            '2026_06_04_090000_add_answer_key_and_plagiarism_toggle_to_perpustakaan_literasi_questions_table.php',
            '2026_07_01_090000_add_soft_deletes_to_perpustakaan_literasi_tables.php',
            '2026_07_07_090000_add_literasi_numerasi_program_fields.php',
            '2026_07_30_090000_create_perpustakaan_literasi_dispensations_table.php',
        ] as $file) {
            $migration = require database_path('migrations/'.$file);
            $migration->up();
        }

        if (! Schema::hasColumn('perpustakaan_literasi_materials', 'opens_at')) {
            Schema::table('perpustakaan_literasi_materials', function (Blueprint $table): void {
                $table->date('opens_at')->nullable();
            });
        }
    }
}
