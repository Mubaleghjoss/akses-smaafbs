<?php

namespace App\Console\Commands;

use App\Enums\Assessment\AssessmentType;
use App\Models\Assessment\ReportTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class InstallAssessmentDefaults extends Command
{
    protected $signature = 'assessment:install-defaults';

    protected $description = 'Memasang role, permission, dan template standar modul Penilaian secara idempoten';

    /**
     * @var list<string>
     */
    public const PERMISSIONS = [
        'penilaian.view',
        'penilaian.manage',
        'penilaian.input',
        'penilaian.submit',
        'penilaian.verify',
        'penilaian.homeroom',
        'penilaian.period.manage',
        'penilaian.report.generate',
        'penilaian.publish',
        'penilaian.audit.view',
    ];

    /**
     * @var array<string, list<string>>
     */
    public const ROLE_PERMISSIONS = [
        'super_admin' => self::PERMISSIONS,
        'admin' => self::PERMISSIONS,
        'guru_admin' => self::PERMISSIONS,
        'kurikulum' => [
            'penilaian.view',
            'penilaian.manage',
            'penilaian.verify',
            'penilaian.period.manage',
            'penilaian.report.generate',
            'penilaian.publish',
            'penilaian.audit.view',
        ],
        'guru_mapel' => [
            'penilaian.view',
            'penilaian.input',
            'penilaian.submit',
        ],
        'guru' => [
            'penilaian.view',
            'penilaian.input',
            'penilaian.submit',
        ],
        'wali_kelas' => [
            'penilaian.view',
            'penilaian.homeroom',
        ],
        'kepala_sekolah' => [
            'penilaian.view',
            'penilaian.audit.view',
        ],
    ];

    public function handle(): int
    {
        if (! $this->requiredTablesExist()) {
            $this->components->error('Jalankan migration terlebih dahulu; tabel Penilaian atau permission belum tersedia.');

            return self::FAILURE;
        }

        DB::transaction(function (): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach (self::PERMISSIONS as $permissionName) {
                Permission::findOrCreate($permissionName, 'web');
            }

            foreach (self::ROLE_PERMISSIONS as $roleName => $permissionNames) {
                $role = Role::findOrCreate($roleName, 'web');

                foreach ($permissionNames as $permissionName) {
                    if (! $role->hasPermissionTo($permissionName)) {
                        $role->givePermissionTo($permissionName);
                    }
                }
            }

            foreach ($this->defaultTemplates() as $attributes) {
                ReportTemplate::query()->firstOrCreate(
                    [
                        'code' => $attributes['code'],
                        'version' => $attributes['version'],
                    ],
                    $attributes,
                );
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->components->info('Default Penilaian terpasang tanpa menghapus role, permission, atau template yang sudah ada.');

        return self::SUCCESS;
    }

    protected function requiredTablesExist(): bool
    {
        $permissionTables = config('permission.table_names', []);

        return collect([
            $permissionTables['roles'] ?? 'roles',
            $permissionTables['permissions'] ?? 'permissions',
            $permissionTables['role_has_permissions'] ?? 'role_has_permissions',
            'assessment_report_templates',
        ])->every(fn (string $table): bool => Schema::hasTable($table));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function defaultTemplates(): array
    {
        $baseSettings = [
            'paper' => 'a4',
            'orientation' => 'portrait',
            'school_name' => 'SMA AFBS',
            'school_address' => null,
            'place' => null,
            'principal_name' => null,
            'principal_identifier' => null,
            'score_label' => 'Nilai Akhir',
            'predicate_label' => 'Predikat',
            'homeroom_title' => 'Wali Kelas',
            'parent_signature_label' => 'Orang Tua/Wali',
            'principal_signature_label' => 'Kepala Sekolah',
            'show_predicate' => true,
            'show_description' => true,
        ];

        return [
            [
                'code' => 'ASTS-STANDARD',
                'type' => AssessmentType::ASTS,
                'name' => 'Rapor ASTS Standar',
                'version' => 1,
                'view_path' => 'assessment.reports.asts',
                'settings' => $baseSettings + [
                    'report_title' => 'LAPORAN HASIL ASESMEN TENGAH SEMESTER',
                ],
                'is_active' => true,
                'effective_from' => null,
            ],
            [
                'code' => 'ASAS-STANDARD',
                'type' => AssessmentType::ASAS,
                'name' => 'Rapor Semester Standar',
                'version' => 1,
                'view_path' => 'assessment.reports.asas',
                'settings' => $baseSettings + [
                    'report_title' => 'LAPORAN HASIL ASESMEN AKHIR SEMESTER',
                ],
                'is_active' => true,
                'effective_from' => null,
            ],
        ];
    }
}
