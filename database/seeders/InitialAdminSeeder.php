<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class InitialAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'users.view',
            'users.manage',
            'data_siswa.view',
            'data_siswa.manage',
            'jenis_berkas.view',
            'jenis_berkas.manage',
            'berkas_siswa.view',
            'berkas_siswa.manage',
            'guru_tendik.view',
            'guru_tendik.manage',
            'berkas_guru.view',
            'berkas_guru.manage',
            'prestasi.view',
            'prestasi.manage',
            'uks_records.view',
            'uks_records.manage',
            'boarding_rapot.view',
            'boarding_rapot.manage',
            'boarding_pencapaian.view',
            'boarding_pencapaian.manage',
            'boarding_konseling.view',
            'boarding_konseling.manage',
            'boarding_keuangan.view',
            'boarding_keuangan.manage',
            'boarding_arsip.view',
            'boarding_arsip.manage',
            'boarding_perizinan.view',
            'boarding_perizinan.manage',
            'catatan_bk.view',
            'catatan_bk.manage',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $rolePermissions = [
            'admin' => $permissions,
            'tu' => [
                'data_siswa.view',
                'data_siswa.manage',
                'jenis_berkas.view',
                'jenis_berkas.manage',
                'berkas_siswa.view',
                'berkas_siswa.manage',
                'prestasi.view',
                'prestasi.manage',
                'boarding_rapot.view',
                'boarding_rapot.manage',
                'boarding_pencapaian.view',
                'boarding_konseling.view',
                'boarding_arsip.view',
                'boarding_arsip.manage',
                'boarding_perizinan.view',
                'boarding_perizinan.manage',
            'catatan_bk.view',
            'catatan_bk.manage',
            ],
            'bendahara' => [
                'data_siswa.view',
                'boarding_keuangan.view',
                'boarding_keuangan.manage',
                'boarding_rapot.view',
            ],
            'pamong_putra' => [
                'boarding_rapot.view',
                'boarding_rapot.manage',
                'boarding_pencapaian.view',
                'boarding_pencapaian.manage',
                'boarding_konseling.view',
                'boarding_konseling.manage',
                'boarding_keuangan.view',
                'boarding_keuangan.manage',
                'boarding_arsip.view',
                'boarding_perizinan.view',
                'boarding_perizinan.manage',
            'catatan_bk.view',
            'catatan_bk.manage',
            ],
            'pamong_putri' => [
                'boarding_rapot.view',
                'boarding_rapot.manage',
                'boarding_pencapaian.view',
                'boarding_pencapaian.manage',
                'boarding_konseling.view',
                'boarding_konseling.manage',
                'boarding_keuangan.view',
                'boarding_keuangan.manage',
                'boarding_arsip.view',
                'boarding_perizinan.view',
                'boarding_perizinan.manage',
            'catatan_bk.view',
            'catatan_bk.manage',
            ],
            'guru' => [
                'guru_tendik.view',
                'guru_tendik.manage',
                'berkas_guru.view',
                'berkas_guru.manage',
            ],
            'kepala_perpus' => [],
            'guru_uks' => [
                'uks_records.view',
                'uks_records.manage',
            ],
        ];

        foreach ($rolePermissions as $roleName => $mappedPermissions) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($mappedPermissions);
        }

        $username = (string) env('INITIAL_ADMIN_USERNAME', 'putra');
        $name = (string) env('INITIAL_ADMIN_NAME', 'Putra');
        $password = env('INITIAL_ADMIN_PASSWORD');

        if (! is_string($password) || trim($password) === '') {
            $this->command?->warn('INITIAL_ADMIN_PASSWORD tidak di-set, seeder dilewati.');

            return;
        }

        $user = User::firstOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'email' => null,
                'password' => Hash::make($password),
            ]
        );

        if (! $user->hasRole('admin')) {
            $user->syncRoles(['admin']);
        }
    }
}

