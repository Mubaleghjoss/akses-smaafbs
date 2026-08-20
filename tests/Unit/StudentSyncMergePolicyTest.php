<?php

namespace Tests\Unit;

use App\Support\StudentSync\StudentSyncMergePolicy;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class StudentSyncMergePolicyTest extends TestCase
{
    public function test_non_empty_local_values_patch_different_server_values(): void
    {
        $policy = app(StudentSyncMergePolicy::class);

        $patch = $policy->patch(
            ['nama' => '  Nama Baru  ', 'nisn' => '001', 'tanggal_lahir' => '', 'status' => 'aktif'],
            ['nama' => 'Nama Lama', 'nisn' => null, 'tanggal_lahir' => '2010-01-01', 'status' => 'aktif'],
            ['nama', 'nisn', 'tanggal_lahir', 'status'],
        );

        $this->assertSame(['nama' => 'Nama Baru', 'nisn' => '001'], $patch);
    }

    public function test_denied_and_unshared_columns_are_never_patched(): void
    {
        $policy = app(StudentSyncMergePolicy::class);

        $patch = $policy->patch(
            [
                'id' => 99,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
                'status' => 'alumni',
                'kategori_non_aktif' => 'lulus',
                'alasan_non_aktif' => 'selesai',
                'tanggal_non_aktif' => '2026-01-01',
                'unknown' => 'unsafe',
                'nama' => 'Aman',
            ],
            ['nama' => 'Lama'],
            [
                'id', 'created_at', 'updated_at', 'status', 'kategori_non_aktif',
                'alasan_non_aktif', 'tanggal_non_aktif', 'nama',
            ],
        );

        $this->assertSame(['nama' => 'Aman'], $patch);
    }

    public function test_dates_booleans_and_empty_values_are_normalized_before_comparison(): void
    {
        $policy = app(StudentSyncMergePolicy::class);

        $patch = $policy->patch(
            [
                'tanggal_lahir' => CarbonImmutable::parse('2010-01-01 17:30:00'),
                'is_boarding' => false,
                'is_active' => true,
                'jumlah_saudara' => 0,
                'nama_ayah' => " \t ",
                'nama_ibu' => null,
            ],
            [
                'tanggal_lahir' => '2010-01-01 00:00:00',
                'is_boarding' => 0,
                'is_active' => 0,
                'jumlah_saudara' => 2,
                'nama_ayah' => 'Ayah Lama',
                'nama_ibu' => 'Ibu Lama',
            ],
            ['tanggal_lahir', 'is_boarding', 'is_active', 'jumlah_saudara', 'nama_ayah', 'nama_ibu'],
        );

        $this->assertSame([
            'is_active' => true,
            'jumlah_saudara' => 0,
        ], $patch);
    }
}
