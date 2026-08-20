<?php

namespace Tests\Feature;

use App\Filament\Pages\BuatAkunSiswa;
use App\Filament\Resources\DataSiswaResource\Pages\PushDataSiswasToServer;
use App\Models\User;
use App\Services\HotspotStudentAccounts;
use App\Support\StudentSync\StudentSyncScopeToken;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery;
use Symfony\Component\Process\Process;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class HotspotStudentPushShortcutTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        $migration = require database_path('migrations/2026_08_18_000003_create_hh_settings_table.php');
        $migration->up();
        $hotspotMigration = require database_path('migrations/2026_08_18_000001_create_hotspot_users_table.php');
        $hotspotMigration->up();
        Schema::create('data_siswa', function (Blueprint $table): void {
            $table->id();
            $table->string('nama')->nullable();
            $table->string('rombel_saat_ini')->nullable();
            $table->string('nipd')->nullable();
            $table->string('nisn')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
        Cache::flush();
    }

    public function test_scope_token_normalizes_ids_and_is_bound_to_one_user(): void
    {
        $owner = $this->user('shortcut-owner');
        $other = $this->user('shortcut-other');
        $token = app(StudentSyncScopeToken::class)->issue([12, '12', 0, -3, 'invalid', 34], $owner->id);

        $this->assertSame([], app(StudentSyncScopeToken::class)->consume($token, $other->id));
        $this->assertSame([12, 34], app(StudentSyncScopeToken::class)->consume($token, $owner->id));
    }

    public function test_account_creation_result_only_includes_successful_source_student_ids(): void
    {
        $manager = Mockery::mock('overload:App\\Services\\HotspotManager');
        $manager->shouldReceive('connect')->once()->andReturnTrue();
        $manager->shouldReceive('routerUsers')->once()->andReturn(['skipped' => []]);
        $manager->shouldReceive('addUser')->twice()->andReturnUsing(
            fn (array $item): array => $item['username'] === 'created' ? ['ok' => true] : ['ok' => false, 'msg' => 'router gagal'],
        );
        $manager->shouldReceive('close')->once();

        $result = HotspotStudentAccounts::createAccounts([
            ['student_id' => 123, 'username' => 'created', 'password' => 'password', 'nama' => 'Created'],
            ['student_id' => 456, 'username' => 'failed', 'password' => 'password', 'nama' => 'Failed'],
            ['student_id' => 789, 'username' => 'skipped', 'password' => 'password', 'nama' => 'Skipped'],
        ], 'default');

        $this->assertSame(1, $result['done']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame([123], $result['student_ids']);
    }

    public function test_scope_token_is_one_time_and_fails_closed_at_expiry(): void
    {
        $owner = $this->user('shortcut-expiry');
        $now = Carbon::parse('2026-08-20 12:00:00 UTC');
        Carbon::setTestNow($now);

        try {
            $token = app(StudentSyncScopeToken::class)->issue([12], $owner->id);
            $this->assertSame([12], app(StudentSyncScopeToken::class)->consume($token, $owner->id));
            $this->assertSame([], app(StudentSyncScopeToken::class)->consume($token, $owner->id));

            $expired = app(StudentSyncScopeToken::class)->issue([34], $owner->id);
            Carbon::setTestNow($now->addMinutes(15));
            $this->assertSame([], app(StudentSyncScopeToken::class)->consume($expired, $owner->id));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_database_cache_allows_exactly_one_concurrent_claimant(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'student-shortcut-race-');
        $barrier = $database.'.start';
        $originalCacheStore = config('cache.default');
        $this->configureDatabaseCache($database);
        Schema::connection('shortcut_race')->create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value');
            $table->integer('expiration');
        });
        Schema::connection('shortcut_race')->create('cache_locks', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        try {
            $token = app(StudentSyncScopeToken::class)->issue([98], 7654321);
            $script = base64_encode(<<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.connections.shortcut_race' => ['driver' => 'sqlite', 'database' => $argv[1], 'prefix' => ''], 'cache.stores.shortcut_race' => ['driver' => 'database', 'connection' => 'shortcut_race', 'table' => 'cache', 'lock_connection' => 'shortcut_race', 'lock_table' => 'cache_locks'], 'cache.default' => 'shortcut_race']);
Illuminate\Support\Facades\DB::purge('shortcut_race');
Illuminate\Support\Facades\Cache::forgetDriver('shortcut_race');
while (! file_exists($argv[4])) { usleep(1000); }
echo json_encode(app(App\Support\StudentSync\StudentSyncScopeToken::class)->consume($argv[2], (int) $argv[3]));
PHP);
            $command = [PHP_BINARY, '-r', "eval(base64_decode('{$script}'));", $database, $token, '7654321', $barrier];
            $first = new Process($command, base_path(), null, null, 10);
            $second = new Process($command, base_path(), null, null, 10);
            $first->start();
            $second->start();
            touch($barrier);
            $first->wait();
            $second->wait();

            $this->assertTrue($first->isSuccessful(), $first->getErrorOutput());
            $this->assertTrue($second->isSuccessful(), $second->getErrorOutput());
            $claims = [json_decode($first->getOutput(), true), json_decode($second->getOutput(), true)];
            sort($claims);
            $this->assertSame([[], [98]], $claims);
        } finally {
            config(['cache.default' => $originalCacheStore]);
            Cache::forgetDriver('shortcut_race');
            @unlink($barrier);
            @unlink($database);
        }
    }

    public function test_push_page_consumes_shortcut_token_once_without_widening_or_auto_apply(): void
    {
        $admin = $this->user('shortcut-admin');
        $admin->assignRole('admin');
        $token = app(StudentSyncScopeToken::class)->issue([55], $admin->id);

        Livewire::actingAs($admin)
            ->test(PushDataSiswasToServer::class, ['scope_token' => $token])
            ->assertSet('scopeIds', [55])
            ->assertSet('hasInvalidScope', false)
            ->assertSet('previewToken', null)
            ->assertSet('applyResult', null);

        Livewire::actingAs($admin)
            ->test(PushDataSiswasToServer::class, ['scope_token' => $token])
            ->assertSet('scopeIds', [])
            ->assertSet('hasInvalidScope', true)
            ->assertSet('previewToken', null)
            ->assertSet('applyResult', null);
    }

    public function test_hotspot_page_generates_a_push_preview_url_only_for_successful_source_student_ids(): void
    {
        $admin = $this->user('shortcut-page-admin');
        $admin->assignRole('admin');

        $page = Livewire::actingAs($admin)->test(BuatAkunSiswa::class)
            ->set('lastCreationResult', [
                'done' => 1,
                'skipped' => 1,
                'failed' => ['siswa2: gagal'],
                'student_ids' => [123],
            ])
            ->call('buildStudentPushShortcut');

        $url = (string) $page->get('studentPushShortcutUrl');
        $this->assertStringContainsString('/push-server?', $url);
        $this->assertStringContainsString('scope_token=', $url);
        $this->assertStringNotContainsString('123', $url);
        $this->assertStringNotContainsString('siswa2', $url);
    }

    public function test_rendered_shortcut_hides_password_and_raw_token_while_linking_to_opaque_url(): void
    {
        $admin = $this->user('shortcut-render-admin');
        $admin->assignRole('admin');
        $password = 'password-only-in-hotspot-preview';
        $rawStudentId = 987654;

        $page = Livewire::actingAs($admin)->test(BuatAkunSiswa::class)
            ->set('candidates', [['id' => $rawStudentId, 'nama' => 'Shortcut Test', 'rombel' => 'X', 'username' => 'shortcut-user', 'password' => $password]])
            ->set('candidates', [])
            ->set('lastCreationResult', ['student_ids' => [$rawStudentId]])
            ->call('buildStudentPushShortcut');

        $url = (string) $page->get('studentPushShortcutUrl');
        $token = (string) Str::of(parse_url($url, PHP_URL_QUERY) ?? '')
            ->after('scope_token=');

        $page->assertSeeHtml('href="'.e($url).'"')
            ->assertSee('Preview Push Data Siswa ke Server')
            ->assertDontSeeText($password)
            ->assertDontSeeText((string) $rawStudentId)
            ->assertDontSeeText($token);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{64}$/', $token);
    }

    private function configureDatabaseCache(string $database): void
    {
        config([
            'database.connections.shortcut_race' => ['driver' => 'sqlite', 'database' => $database, 'prefix' => ''],
            'cache.stores.shortcut_race' => [
                'driver' => 'database',
                'connection' => 'shortcut_race',
                'table' => 'cache',
                'lock_connection' => 'shortcut_race',
                'lock_table' => 'cache_locks',
            ],
            'cache.default' => 'shortcut_race',
        ]);
        app('db')->purge('shortcut_race');
        Cache::forgetDriver('shortcut_race');
    }

    private function user(string $username): User
    {
        return User::query()->create([
            'name' => $username,
            'username' => $username,
            'password' => bcrypt('password'),
        ]);
    }
}
