<?php

namespace Tests\Feature;

use App\Filament\Resources\BoardingPerizinanSiswaResource;
use App\Models\BoardingPerizinanSiswa;
use App\Models\DataSiswa;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class BoardingPerizinanSiswaFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createDataSiswaTable();
        $this->runPerizinanMigration();
    }

    public function test_can_create_boarding_perizinan_request_entry(): void
    {
        $siswa = DataSiswa::query()->create([
            'nama' => 'Santri Perizinan',
            'rombel_saat_ini' => 'XI IPA 2',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $record = BoardingPerizinanSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'judul_izin' => 'Jenguk Orang Tua',
            'tanggal_izin' => '2026-03-30',
            'waktu_izin' => '09:15:00',
            'detail_izin' => 'Dijemput wali dari gerbang utama.',
        ]);

        $this->assertDatabaseHas('boarding_perizinan_siswas', [
            'id' => $record->id,
            'siswa_id' => $siswa->id,
            'judul_izin' => 'Jenguk Orang Tua',
            'tanggal_izin' => '2026-03-30 00:00:00',
            'waktu_izin' => '09:15:00',
            'detail_izin' => 'Dijemput wali dari gerbang utama.',
            'status_perizinan' => 'pending',
        ]);
    }

    public function test_leave_title_suggestions_reuse_previous_titles(): void
    {
        $siswa = DataSiswa::query()->create([
            'nama' => 'Santri Saran Judul',
            'rombel_saat_ini' => 'XII IPA 1',
            'jk' => 'P',
            'status' => 'aktif',
        ]);

        BoardingPerizinanSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'judul_izin' => 'Kontrol Kesehatan',
            'tanggal_izin' => '2026-03-29',
        ]);

        BoardingPerizinanSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'judul_izin' => 'Kontrol Kesehatan',
            'tanggal_izin' => '2026-03-30',
        ]);

        BoardingPerizinanSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'judul_izin' => 'Acara Keluarga',
            'tanggal_izin' => '2026-03-31',
        ]);

        $suggestions = BoardingPerizinanSiswaResource::leaveTitleSuggestions();

        $this->assertContains('Kontrol Kesehatan', $suggestions);
        $this->assertContains('Acara Keluarga', $suggestions);
        $this->assertSame(1, count(array_keys($suggestions, 'Kontrol Kesehatan', true)));
    }

    public function test_return_completion_persists_follow_up_fields(): void
    {
        $siswa = DataSiswa::query()->create([
            'nama' => 'Santri Kepulangan',
            'rombel_saat_ini' => 'XI IPS 1',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $record = BoardingPerizinanSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'judul_izin' => 'Izin Acara Desa',
            'tanggal_izin' => '2026-03-30',
        ]);

        $this->assertDatabaseHas('boarding_perizinan_siswas', [
            'id' => $record->id,
            'status_perizinan' => 'pending',
            'tanggal_kembali' => null,
        ]);

        $record->update([
            'tanggal_kembali' => '2026-03-31',
            'waktu_kembali' => '19:20:00',
            'detail_kembali' => 'Kembali dengan aman bersama wali.',
            'kafaroh_keterlambatan' => 'Tilawah tambahan 1 juz.',
        ]);

        $this->assertSame(1, BoardingPerizinanSiswa::query()->count());
        $this->assertDatabaseHas('boarding_perizinan_siswas', [
            'id' => $record->id,
            'tanggal_kembali' => '2026-03-31 00:00:00',
            'waktu_kembali' => '19:20:00',
            'detail_kembali' => 'Kembali dengan aman bersama wali.',
            'kafaroh_keterlambatan' => 'Tilawah tambahan 1 juz.',
            'status_perizinan' => 'selesai',
        ]);
    }

    public function test_return_summary_label_surfaces_return_and_kafaroh_data_for_main_table(): void
    {
        $siswa = DataSiswa::query()->create([
            'nama' => 'Santri Ringkasan',
            'rombel_saat_ini' => 'XI IPA 1',
            'jk' => 'P',
            'status' => 'aktif',
        ]);

        $record = BoardingPerizinanSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'judul_izin' => 'Izin Keluarga',
            'tanggal_izin' => '2026-03-28',
            'tanggal_kembali' => '2026-03-29',
            'waktu_kembali' => '20:30:00',
            'kafaroh_keterlambatan' => 'Tilawah 1 juz.',
        ]);

        $summary = BoardingPerizinanSiswaResource::returnSummaryLabel($record->fresh());

        $this->assertStringContainsString('Kembali: 29 Mar 2026 • 20:30', $summary);
        $this->assertStringContainsString('Kafaroh: Tilawah 1 juz.', $summary);
    }

    public function test_history_query_source_filters_entries_by_selected_student(): void
    {
        $siswaA = DataSiswa::query()->create([
            'nama' => 'Santri A',
            'rombel_saat_ini' => 'X IPA 1',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $siswaB = DataSiswa::query()->create([
            'nama' => 'Santri B',
            'rombel_saat_ini' => 'X IPA 2',
            'jk' => 'P',
            'status' => 'aktif',
        ]);

        $recordA1 = BoardingPerizinanSiswa::query()->create([
            'siswa_id' => $siswaA->id,
            'judul_izin' => 'Izin A1',
            'tanggal_izin' => '2026-03-28',
        ]);

        $recordA2 = BoardingPerizinanSiswa::query()->create([
            'siswa_id' => $siswaA->id,
            'judul_izin' => 'Izin A2',
            'tanggal_izin' => '2026-03-30',
        ]);

        BoardingPerizinanSiswa::query()->create([
            'siswa_id' => $siswaB->id,
            'judul_izin' => 'Izin B1',
            'tanggal_izin' => '2026-03-31',
        ]);

        $historyIds = BoardingPerizinanSiswaResource::historyQueryForStudent($siswaA->id, null)
            ->pluck('id')
            ->all();

        $this->assertSame([$recordA2->id, $recordA1->id], $historyIds);
    }

    public function test_status_accessor_falls_back_to_tanggal_kembali_when_runtime_column_is_missing(): void
    {
        $record = new BoardingPerizinanSiswa([
            'tanggal_kembali' => '2026-03-31',
        ]);

        $this->assertSame('selesai', $record->status_perizinan);
    }

    public function test_runtime_accessors_fall_back_to_legacy_pickup_and_return_note_columns(): void
    {
        $record = new BoardingPerizinanSiswa([
            'waktu_jemput' => '08:15:00',
            'catatan_kembali' => 'Kembali bersama wali.',
        ]);

        $this->assertSame('08:15:00', $record->waktu_izin);
        $this->assertSame('Kembali bersama wali.', $record->detail_kembali);
    }

    public function test_prefers_legacy_pamong_user_id_for_visibility_scope_when_available(): void
    {
        Schema::table('boarding_perizinan_siswas', function (Blueprint $table): void {
            $table->unsignedBigInteger('pamong_user_id')->nullable()->after('siswa_id');
        });

        $this->resetBoardingPerizinanRuntimeCaches();

        $this->assertSame('pamong_user_id', BoardingPerizinanSiswa::resolvePamongOwnershipColumn());
    }

    public function test_return_completion_payload_degrades_safely_on_legacy_runtime_schema(): void
    {
        $this->recreatePerizinanTableWithLegacyReturnShape();
        $this->resetBoardingPerizinanRuntimeCaches();

        $siswa = DataSiswa::query()->create([
            'nama' => 'Santri Legacy Runtime',
            'rombel_saat_ini' => 'XI IPS 2',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $record = BoardingPerizinanSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'judul_izin' => 'Izin Legacy',
            'tanggal_izin' => '2026-03-30',
        ]);

        $payload = BoardingPerizinanSiswa::buildReturnCompletionPayload([
            'tanggal_kembali' => '2026-03-31',
            'waktu_kembali' => '20:10:00',
            'detail_kembali' => 'Pulang bersama wali.',
            'kafaroh_keterlambatan' => 'Tilawah 1 juz.',
        ]);

        $this->assertArrayHasKey('tanggal_kembali', $payload);
        $this->assertArrayHasKey('catatan_kembali', $payload);
        $this->assertArrayNotHasKey('waktu_kembali', $payload);
        $this->assertArrayNotHasKey('detail_kembali', $payload);
        $this->assertArrayNotHasKey('kafaroh_keterlambatan', $payload);

        $record->update($payload);

        $this->assertDatabaseHas('boarding_perizinan_siswas', [
            'id' => $record->id,
            'tanggal_kembali' => '2026-03-31 00:00:00',
            'catatan_kembali' => 'Pulang bersama wali.',
        ]);
    }

    protected function createDataSiswaTable(): void
    {
        if (Schema::hasTable('data_siswa')) {
            return;
        }

        Schema::create('data_siswa', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->string('rombel_saat_ini')->nullable();
            $table->string('jk', 2)->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    protected function runPerizinanMigration(): void
    {
        if (Schema::hasTable('boarding_perizinan_siswas')) {
            return;
        }

        $migration = require database_path('migrations/2026_03_30_090000_create_boarding_perizinan_siswas_table.php');
        $migration->up();
    }

    protected function recreatePerizinanTableWithLegacyReturnShape(): void
    {
        Schema::dropIfExists('boarding_perizinan_siswas');

        Schema::create('boarding_perizinan_siswas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('siswa_id');
            $table->string('judul_izin', 150);
            $table->date('tanggal_izin');
            $table->time('waktu_izin')->nullable();
            $table->text('detail_izin')->nullable();
            $table->date('tanggal_kembali')->nullable();
            $table->text('catatan_kembali')->nullable();
            $table->timestamps();
        });
    }

    protected function resetBoardingPerizinanRuntimeCaches(): void
    {
        $reflection = new ReflectionClass(BoardingPerizinanSiswa::class);

        foreach (['dibuatOlehColumnAvailable', 'pamongUserColumnAvailable', 'legacyPickupTimeColumnAvailable', 'legacyReturnNoteColumnAvailable', 'diprosesOlehColumnAvailable', 'statusPerizinanColumnAvailable', 'runtimeColumnAvailability'] as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            $property->setValue(null, $propertyName === 'runtimeColumnAvailability' ? [] : null);
        }
    }
}
