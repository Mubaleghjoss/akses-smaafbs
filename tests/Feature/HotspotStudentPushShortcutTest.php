<?php

namespace Tests\Feature;

use App\Filament\Pages\BuatAkunSiswa;
use App\Filament\Resources\DataSiswaResource\Pages\PushDataSiswasToServer;
use App\Models\User;
use App\Services\HotspotStudentAccounts;
use App\Support\StudentSync\StudentSyncScopeToken;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
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
        $scopeTokenMigration = require database_path('migrations/2026_08_20_130000_create_student_sync_scope_tokens_table.php');
        $scopeTokenMigration->up();
    }

    public function test_scope_token_normalizes_ids_and_is_bound_to_one_user(): void
    {
        $owner = $this->user('shortcut-owner');
        $other = $this->user('shortcut-other');
        $token = app(StudentSyncScopeToken::class)->issue([12, '12', 0, -3, 'invalid', 34], $owner->id);

        $this->assertSame([], app(StudentSyncScopeToken::class)->consume($token, $other->id));
        $this->assertSame([12, 34], app(StudentSyncScopeToken::class)->consume($token, $owner->id));
    }

    public function test_scope_token_test_hook_is_ignored_outside_the_testing_environment(): void
    {
        $calls = 0;
        $environment = app()->environment();
        app()->detectEnvironment(static fn (): string => 'production');
        config(['student_sync.scope_token_test_hook' => function () use (&$calls): void {
            $calls++;
        }]);

        try {
            $token = app(StudentSyncScopeToken::class)->issue([12], 7654321);

            $this->assertSame([12], app(StudentSyncScopeToken::class)->consume($token, 7654321));
            $this->assertSame(0, $calls);
        } finally {
            app()->detectEnvironment(static fn (): string => $environment);
            config(['student_sync.scope_token_test_hook' => null]);
        }
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

    public function test_durable_token_claim_allows_one_claimant_even_when_first_is_delayed_past_old_lock_lease(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'student-shortcut-claim-');
        $claimed = $database.'.claimed';
        $contenderEntered = $database.'.contender-entered';
        $contenderRelease = $database.'.contender-release';
        $firstRelease = $database.'.first-release';
        $first = null;
        $second = null;
        try {
            config(['database.connections.shortcut_claim' => ['driver' => 'sqlite', 'database' => $database, 'prefix' => '']]);
            app('db')->purge('shortcut_claim');
            Schema::connection('shortcut_claim')->create('student_sync_scope_tokens', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('token_hash', 64)->unique();
                $table->longText('encrypted_student_ids');
                $table->timestamp('expires_at')->index();
                $table->timestamp('consumed_at')->nullable()->index();
                $table->timestamps();
            });
            $token = (new StudentSyncScopeToken('shortcut_claim'))->issue([98], 7654321);
            $script = base64_encode(<<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['app.key' => $argv[9]]);
$app->forgetInstance('encrypter');
config([
    'app.env' => 'testing',
    'database.connections.shortcut_claim' => ['driver' => 'sqlite', 'database' => $argv[1], 'prefix' => ''],
    'student_sync.scope_token_test_hook' => function (string $stage) use ($argv): void {
        if ($argv[5] === 'first' && $stage === 'after_claim') {
            touch($argv[4]);
            while (! file_exists($argv[8])) { usleep(1000); }
        }

        if ($argv[5] === 'second' && $stage === 'before_database') {
            touch($argv[6]);
            while (! file_exists($argv[7])) { usleep(1000); }
        }
    },
]);
Illuminate\Support\Facades\DB::purge('shortcut_claim');
echo json_encode((new App\Support\StudentSync\StudentSyncScopeToken('shortcut_claim'))->consume($argv[2], (int) $argv[3]));
PHP);
            $appKey = (string) config('app.key');
            $firstCommand = [PHP_BINARY, '-r', "eval(base64_decode('{$script}'));", $database, $token, '7654321', $claimed, 'first', $contenderEntered, $contenderRelease, $firstRelease, $appKey];
            $secondCommand = [PHP_BINARY, '-r', "eval(base64_decode('{$script}'));", $database, $token, '7654321', $claimed, 'second', $contenderEntered, $contenderRelease, $firstRelease, $appKey];
            $first = new Process($firstCommand, base_path(), null, null, 20);
            $first->start();
            $this->waitForFile($claimed, $first, 'first claimant marker');

            $second = new Process($secondCommand, base_path(), null, null, 20);
            $second->start();
            $this->waitForFile($contenderEntered, $second, 'second consume inner-hook marker');

            sleep(7);
            touch($contenderRelease);
            touch($firstRelease);
            $first->wait();
            $second->wait();

            $this->assertTrue($first->isSuccessful(), $first->getErrorOutput());
            $this->assertTrue($second->isSuccessful(), $second->getErrorOutput());
            $claims = [json_decode($first->getOutput(), true), json_decode($second->getOutput(), true)];
            sort($claims);
            $this->assertSame([[], [98]], $claims);
        } finally {
            touch($contenderRelease);
            touch($firstRelease);
            $this->stopProcess($second);
            $this->stopProcess($first);
            @unlink($claimed);
            @unlink($contenderEntered);
            @unlink($contenderRelease);
            @unlink($firstRelease);
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

    private function waitForFile(string $path, Process $process, string $marker): void
    {
        $deadline = microtime(true) + 10;

        while (! file_exists($path) && microtime(true) < $deadline) {
            if (! $process->isRunning()) {
                $this->fail("{$marker} process stopped: ".$process->getErrorOutput());
            }

            usleep(1000);
        }

        $this->assertFileExists($path, "Timed out waiting for {$marker}.");
    }

    private function stopProcess(?Process $process): void
    {
        if ($process !== null && $process->isRunning()) {
            $process->stop(1);
        }
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
