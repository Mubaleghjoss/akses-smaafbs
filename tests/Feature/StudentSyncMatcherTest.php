<?php

namespace Tests\Feature;

use App\Models\DataSiswa;
use App\Support\StudentSync\StudentSyncMatcher;
use App\Support\StudentSync\StudentSyncMatchResult;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentSyncMatcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('data_siswa');
        Schema::create('data_siswa', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->string('nipd')->nullable();
            $table->string('nisn')->nullable();
            $table->string('billing_code')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->timestamps();
        });
    }

    public function test_same_id_plus_matching_nipd_returns_matched(): void
    {
        $this->student(10, 'Alya', 'P001', 'N001', 'B001', '2010-01-01');

        $result = app(StudentSyncMatcher::class)->match(['id' => 10, 'nipd' => ' P001 ']);

        $this->assertSame(StudentSyncMatchResult::MATCHED, $result->status);
        $this->assertInstanceOf(DataSiswa::class, $result->matched);
        $this->assertSame(10, $result->matched->getKey());
        $this->assertSame('matched_by_id', $result->reason);
        $this->assertSame(['id' => 10, 'nipd' => 'P001'], $result->evidence);
    }

    public function test_different_id_but_unique_nipd_returns_matched(): void
    {
        $this->student(10, 'Alya', 'P001', null, null, '2010-01-01');

        $result = app(StudentSyncMatcher::class)->match(['id' => 99, 'nipd' => 'P001']);

        $this->assertSame(StudentSyncMatchResult::MATCHED, $result->status);
        $this->assertSame(10, $result->matched?->getKey());
        $this->assertSame('matched_by_strong_identifier', $result->reason);
        $this->assertSame(['nipd' => 'P001'], $result->evidence);
    }

    public function test_unique_nisn_returns_matched(): void
    {
        $this->student(10, 'Alya', null, 'N001', null, '2010-01-01');

        $result = app(StudentSyncMatcher::class)->match(['nisn' => 'N001']);

        $this->assertSame(StudentSyncMatchResult::MATCHED, $result->status);
        $this->assertSame(10, $result->matched?->getKey());
        $this->assertSame(['nisn' => 'N001'], $result->evidence);
    }

    public function test_unique_billing_code_returns_matched(): void
    {
        $this->student(10, 'Alya', null, null, 'B001', '2010-01-01');

        $result = app(StudentSyncMatcher::class)->match(['billing_code' => 'B001']);

        $this->assertSame(StudentSyncMatchResult::MATCHED, $result->status);
        $this->assertSame(10, $result->matched?->getKey());
        $this->assertSame(['billing_code' => 'B001'], $result->evidence);
    }

    public function test_normalized_name_and_date_fallback_returns_unique_match(): void
    {
        $this->student(10, 'Alya   Putri', null, null, null, '2010-01-02');

        $result = app(StudentSyncMatcher::class)->match([
            'nama' => '  ALYA putri ',
            'tanggal_lahir' => '2010-01-02 12:30:00',
        ]);

        $this->assertSame(StudentSyncMatchResult::MATCHED, $result->status);
        $this->assertSame(10, $result->matched?->getKey());
        $this->assertSame('matched_by_name_and_dob', $result->reason);
        $this->assertSame([
            'nama' => 'alya putri',
            'tanggal_lahir' => '2010-01-02',
        ], $result->evidence);
    }

    public function test_strong_identifiers_resolving_to_two_candidates_return_conflict(): void
    {
        $this->student(10, 'Alya', 'P001', null, null, '2010-01-01');
        $this->student(20, 'Bella', null, 'N002', null, '2010-02-01');

        $result = app(StudentSyncMatcher::class)->match(['nipd' => 'P001', 'nisn' => 'N002']);

        $this->assertSame(StudentSyncMatchResult::CONFLICT, $result->status);
        $this->assertNull($result->matched);
        $this->assertSame('multiple_strong_candidates', $result->reason);
        $this->assertSame(['candidate_ids' => [10, 20]], $result->evidence);
    }

    public function test_same_id_with_contradictory_strong_identifiers_returns_conflict(): void
    {
        $this->student(10, 'Alya', 'P001', 'N001', null, '2010-01-01');

        $result = app(StudentSyncMatcher::class)->match([
            'id' => 10,
            'nipd' => 'P001',
            'nisn' => 'OTHER',
        ]);

        $this->assertSame(StudentSyncMatchResult::CONFLICT, $result->status);
        $this->assertNull($result->matched);
        $this->assertSame('contradictory_strong_identifiers', $result->reason);
        $this->assertSame([
            'id' => 10,
            'conflicts' => ['nisn' => ['source' => 'OTHER', 'target' => 'N001']],
        ], $result->evidence);
    }

    public function test_id_without_matching_strong_evidence_does_not_match(): void
    {
        $this->student(10, 'Alya', 'P001', null, null, '2010-01-01');

        $result = app(StudentSyncMatcher::class)->match(['id' => 10, 'nama' => 'Alya']);

        $this->assertSame(StudentSyncMatchResult::NOT_FOUND, $result->status);
        $this->assertNull($result->matched);
        $this->assertSame('insufficient_id_evidence', $result->reason);
        $this->assertSame(['id' => 10], $result->evidence);
    }

    public function test_duplicate_name_and_date_fallback_returns_conflict(): void
    {
        $this->student(10, 'Alya', null, null, null, '2010-01-01');
        $this->student(20, ' ALYA ', null, null, null, '2010-01-01');

        $result = app(StudentSyncMatcher::class)->match([
            'nama' => 'alya',
            'tanggal_lahir' => '2010-01-01',
        ]);

        $this->assertSame(StudentSyncMatchResult::CONFLICT, $result->status);
        $this->assertNull($result->matched);
        $this->assertSame('ambiguous_name_and_dob', $result->reason);
        $this->assertSame(['candidate_ids' => [10, 20]], $result->evidence);
    }

    public function test_no_candidate_returns_not_found(): void
    {
        $result = app(StudentSyncMatcher::class)->match(['nipd' => 'MISSING']);

        $this->assertSame(StudentSyncMatchResult::NOT_FOUND, $result->status);
        $this->assertNull($result->matched);
        $this->assertSame('no_candidate', $result->reason);
        $this->assertSame(['nipd' => 'MISSING'], $result->evidence);
    }

    private function student(
        int $id,
        string $nama,
        ?string $nipd,
        ?string $nisn,
        ?string $billingCode,
        ?string $tanggalLahir,
    ): void {
        DB::table('data_siswa')->insert([
            'id' => $id,
            'nama' => $nama,
            'nipd' => $nipd,
            'nisn' => $nisn,
            'billing_code' => $billingCode,
            'tanggal_lahir' => $tanggalLahir,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
