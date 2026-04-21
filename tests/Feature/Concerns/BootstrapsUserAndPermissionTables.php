<?php

namespace Tests\Feature\Concerns;

use Database\Seeders\InitialAdminSeeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

trait BootstrapsUserAndPermissionTables
{
    protected function bootstrapUserAndPermissionTables(): void
    {
        $this->runUserMigrations();
        $this->runPermissionMigration();
        (new InitialAdminSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function runUserMigrations(): void
    {
        if (! Schema::hasTable('users')) {
            $migration = require database_path('migrations/0001_01_01_000000_create_users_table.php');
            $migration->up();

            $scopeMigration = require database_path('migrations/2026_03_25_230000_add_boarding_scope_to_users_table.php');
            $scopeMigration->up();

            $rombelScopeMigration = require database_path('migrations/2026_03_26_160000_add_boarding_rombel_and_navigation_scope_to_users_table.php');
            $rombelScopeMigration->up();

            $teacherScopeMigration = require database_path('migrations/2026_03_26_170000_add_teacher_scope_and_navigation_items_to_users_table.php');
            $teacherScopeMigration->up();

            $moduleAccessMigration = require database_path('migrations/2026_03_28_110000_add_module_access_levels_to_users_table.php');
            $moduleAccessMigration->up();

            $defaultPasswordFlagsMigration = require database_path('migrations/2026_03_29_090000_add_default_password_flags_to_users_table.php');
            $defaultPasswordFlagsMigration->up();
        }

        $avatarMigration = require database_path('migrations/2026_04_06_110000_add_avatar_path_and_google_drive_folder_name.php');
        $avatarMigration->up();
    }

    protected function runPermissionMigration(): void
    {
        if (Schema::hasTable('roles')) {
            return;
        }

        $migration = require database_path('migrations/2026_01_12_111708_create_permission_tables.php');
        $migration->up();
    }
}
