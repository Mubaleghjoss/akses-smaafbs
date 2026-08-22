<?php

namespace Tests\Feature;

use App\Models\StudentSyncNonce;
use App\Models\StudentSyncPreview;
use App\Models\StudentSyncRun;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class StudentSyncFoundationTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
    }

    public function test_student_sync_foundation_exposes_safe_defaults_and_tables(): void
    {
        $this->runStudentSyncMigration();

        $this->assertFalse(config('student_sync.receiver.enabled'));
        $this->assertFalse(config('student_sync.client.enabled'));
        $this->assertSame(250, config('student_sync.security.max_batch'));
        $this->assertTrue(Schema::hasTable('student_sync_runs'));
        $this->assertTrue(Schema::hasTable('student_sync_previews'));
        $this->assertTrue(Schema::hasTable('student_sync_nonces'));
    }

    public function test_student_sync_models_expose_required_casts(): void
    {
        $this->assertSame([
            'counts' => 'array',
            'field_summary' => 'array',
            'result_summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ], array_intersect_key((new StudentSyncRun)->getCasts(), array_flip([
            'counts',
            'field_summary',
            'result_summary',
            'started_at',
            'finished_at',
        ])));
        $this->assertSame('encrypted:array', (new StudentSyncPreview)->getCasts()['encrypted_payload']);
        $this->assertSame('datetime', (new StudentSyncPreview)->getCasts()['expires_at']);
        $this->assertSame('datetime', (new StudentSyncPreview)->getCasts()['applied_at']);
        $this->assertSame('datetime', (new StudentSyncNonce)->getCasts()['expires_at']);
    }

    public function test_install_defaults_assigns_push_permission_only_to_existing_full_admin_roles(): void
    {
        Role::findOrCreate('super_admin', 'web');
        Role::findOrCreate('operator', 'web');

        $this->assertSame(0, Artisan::call('student-sync:install-defaults'));
        $this->assertSame(0, Artisan::call('student-sync:install-defaults'));

        $permission = Permission::findByName('data_siswa.push_server', 'web');

        foreach (['admin', 'super_admin', 'guru_admin'] as $roleName) {
            $this->assertTrue(Role::findByName($roleName, 'web')->hasPermissionTo($permission));
        }

        $this->assertFalse(Role::findByName('operator', 'web')->hasPermissionTo($permission));
        $this->assertSame(1, Permission::query()
            ->where('name', 'data_siswa.push_server')
            ->where('guard_name', 'web')
            ->count());
    }

    private function runStudentSyncMigration(): void
    {
        $migration = require database_path(
            'migrations/2026_08_20_120000_create_student_sync_tables.php',
        );
        $migration->up();
    }
}
