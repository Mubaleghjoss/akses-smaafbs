<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class InstallStudentSyncDefaults extends Command
{
    protected $signature = 'student-sync:install-defaults';

    protected $description = 'Install the student sync permission for existing full-admin roles';

    /** @var list<string> */
    private const FULL_ADMIN_ROLES = [
        'admin',
        'super_admin',
        'guru_admin',
    ];

    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate('data_siswa.push_server', 'web');

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', self::FULL_ADMIN_ROLES)
            ->each(function (Role $role) use ($permission): void {
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->components->info('Student sync defaults installed.');

        return self::SUCCESS;
    }
}
