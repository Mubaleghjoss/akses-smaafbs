<?php

namespace Tests\Feature;

use App\Filament\Resources\DataSiswaResource\Pages\ManageDataSiswas;
use App\Filament\Resources\DataSiswaResource\Pages\PushDataSiswasToServer;
use App\Models\User;
use App\Support\StudentSync\StudentPushScopeToken;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class StudentServerPushPageTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        Schema::dropIfExists('data_siswa');
        Schema::create('data_siswa', function (Blueprint $table): void {
            $table->id();
            $table->string('nama')->nullable();
            $table->string('nipd')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('data_siswa');

        parent::tearDown();
    }

    public function test_full_admin_and_authorized_manager_can_access_but_view_only_user_cannot(): void
    {
        $admin = $this->user('push-admin');
        $admin->assignRole('admin');
        $manager = $this->user('push-manager', ['data_siswa' => 'manage']);
        Permission::findOrCreate('data_siswa.push_server', 'web');
        $manager->givePermissionTo('data_siswa.push_server');
        $viewer = $this->user('push-viewer', ['data_siswa' => 'view']);

        $this->assertTrue(PushDataSiswasToServer::canAccessPage($admin));
        $this->assertTrue(PushDataSiswasToServer::canAccessPage($manager));
        $this->assertFalse(PushDataSiswasToServer::canAccessPage($viewer));

        Livewire::actingAs($admin)->test(PushDataSiswasToServer::class)->assertOk();
        Livewire::actingAs($manager)->test(PushDataSiswasToServer::class)->assertOk();
        Livewire::actingAs($viewer)->test(PushDataSiswasToServer::class)->assertForbidden();
    }

    public function test_header_action_requires_enabled_client_and_authorized_user(): void
    {
        $admin = $this->user('header-admin');
        $admin->assignRole('admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        config(['student_sync.client.enabled' => false]);
        $disabled = Livewire::actingAs($admin)->test(ManageDataSiswas::class)->instance();
        $this->assertFalse($this->headerAction($disabled, 'pushToServer')->isVisible());

        config(['student_sync.client.enabled' => true]);
        $enabled = Livewire::actingAs($admin)->test(ManageDataSiswas::class)->instance();
        $this->assertTrue($this->headerAction($enabled, 'pushToServer')->isVisible());
    }

    public function test_enabled_client_does_not_show_header_action_to_unauthorized_user(): void
    {
        $viewer = $this->user('enabled-header-viewer', ['data_siswa' => 'view']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        config(['student_sync.client.enabled' => true]);

        $page = Livewire::actingAs($viewer)->test(ManageDataSiswas::class)->instance();

        $this->assertFalse($this->headerAction($page, 'pushToServer')->isVisible());
    }

    public function test_valid_user_bound_scope_token_narrows_preview_builder_selection(): void
    {
        $admin = $this->user('scoped-push-admin');
        $admin->assignRole('admin');
        $this->student(10, 'aktif');
        $this->student(20, 'aktif');
        config([
            'student_sync.client.enabled' => true,
            'student_sync.client.server_url' => 'https://sync.example.test',
            'student_sync.client.client_id' => 'school-local',
            'student_sync.client.secret' => str_repeat('s', 32),
        ]);
        Http::fake(['https://sync.example.test/api/internal/student-sync/preview' => Http::response([
            'preview_token' => 'scoped-preview-token',
            'payload_checksum' => str_repeat('b', 64),
            'counts' => ['total' => 1],
            'field_summary' => [],
            'items' => [],
        ])]);

        $scope = app(StudentPushScopeToken::class)->forUser($admin, [20]);
        Livewire::actingAs($admin)
            ->test(PushDataSiswasToServer::class, ['scope' => $scope])
            ->call('loadPreview')
            ->assertSet('scopeIds', [20]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sync.example.test/api/internal/student-sync/preview'
            && array_column($request->data()['students'], 'source_id') === [20]);
    }

    public function test_tampered_expired_or_wrong_user_scope_never_widens_preview_selection(): void
    {
        $owner = $this->user('scope-owner');
        $owner->assignRole('admin');
        $other = $this->user('scope-other');
        $other->assignRole('admin');
        $this->student(10, 'aktif');
        $this->student(20, 'aktif');
        config([
            'student_sync.client.enabled' => true,
            'student_sync.client.server_url' => 'https://sync.example.test',
            'student_sync.client.client_id' => 'school-local',
            'student_sync.client.secret' => str_repeat('s', 32),
        ]);
        Http::fake(['https://sync.example.test/api/internal/student-sync/preview' => Http::response([
            'preview_token' => 'unscoped-preview-token',
            'payload_checksum' => str_repeat('c', 64),
            'counts' => ['total' => 2],
            'field_summary' => [],
            'items' => [],
        ])]);

        $valid = app(StudentPushScopeToken::class)->forUser($owner, [20]);
        $expired = app(StudentPushScopeToken::class)->forUser($owner, [20], now()->subSecond());

        foreach ([$valid.'tampered', $expired, $valid] as $index => $scope) {
            $user = $index === 2 ? $other : $owner;
            Livewire::actingAs($user)
                ->test(PushDataSiswasToServer::class, ['scope' => $scope])
                ->call('loadPreview')
                ->assertSet('scopeIds', []);
        }

        Http::assertSentCount(3);
        foreach (Http::recorded() as [$request]) {
            $this->assertSame([10, 20], array_column($request->data()['students'], 'source_id'));
        }
    }

    public function test_load_preview_projects_adversarial_server_response_to_fixed_safe_ui_values(): void
    {
        $admin = $this->user('adversarial-preview-admin');
        $admin->assignRole('admin');
        config([
            'student_sync.client.enabled' => true,
            'student_sync.client.server_url' => 'https://sync.example.test',
            'student_sync.client.client_id' => 'school-local',
            'student_sync.client.secret' => str_repeat('s', 32),
        ]);
        Http::fake(['https://sync.example.test/api/internal/student-sync/preview' => Http::response([
            'preview_token' => 'safe-preview-token',
            'payload_checksum' => str_repeat('d', 64),
            'expires_at' => '2026-08-20T12:30:00+00:00',
            'counts' => ['update' => 2, 'payload-secret-value' => 999],
            'field_summary' => ['nama' => 2, 'before_after_secret' => 999],
            'items' => [[
                'status' => 'update', 'source_id' => 10, 'target_id' => 20,
                'changed_fields' => ['nama', 'raw_payload_secret'],
                'reason' => 'before: SENSITIVE before after: SECRET secret-value',
                'payload' => ['secret' => 'secret-value'],
            ], [
                'status' => 'unknown-value-secret', 'source_id' => 'not-an-id',
                'changed_fields' => ['before_after_secret'], 'reason' => 'secret-value',
            ]],
        ])]);

        Livewire::actingAs($admin)->test(PushDataSiswasToServer::class)
            ->call('loadPreview')
            ->assertSet('previewToken', 'safe-preview-token')
            ->assertSee('Update')
            ->assertSee('nama')
            ->assertDontSee('payload-secret-value')
            ->assertDontSee('raw_payload_secret')
            ->assertDontSee('before_after_secret')
            ->assertDontSee('secret-value')
            ->assertDontSee('SENSITIVE')
            ->assertSet('counts', ['update' => 2])
            ->assertSet('fieldSummary', ['nama' => 2])
            ->assertSet('items.0', [
                'status' => 'update', 'source_id' => 10, 'target_id' => 20,
                'changed_fields' => ['nama'], 'reason' => null,
            ]);
    }

    public function test_load_preview_shows_safe_summary_and_apply_uses_stable_idempotency_key(): void
    {
        $admin = $this->user('preview-admin');
        $admin->assignRole('admin');
        config([
            'student_sync.client.enabled' => true,
            'student_sync.client.server_url' => 'https://sync.example.test',
            'student_sync.client.client_id' => 'school-local',
            'student_sync.client.secret' => str_repeat('s', 32),
        ]);
        Http::fake([
            'https://sync.example.test/api/internal/student-sync/preview' => Http::response([
                'preview_token' => '5a0ee1ab-6462-4c9c-a858-cfd35f8c2d0c',
                'payload_checksum' => str_repeat('a', 64),
                'expires_at' => '2026-08-20T12:30:00+00:00',
                'counts' => ['new' => 1, 'update' => 1, 'unchanged' => 0, 'conflict' => 1],
                'field_summary' => ['nama' => 2, 'nipd' => 1],
                'items' => [[
                    'status' => 'update', 'source_id' => 10, 'target_id' => 20,
                    'changed_fields' => ['nama'], 'reason' => null,
                ]],
            ]),
            'https://sync.example.test/api/internal/student-sync/apply' => Http::response(['counts' => ['created' => 1], 'items' => []]),
        ]);

        $component = Livewire::actingAs($admin)->test(PushDataSiswasToServer::class)
            ->call('loadPreview')
            ->assertSee('nama')
            ->assertDontSee('secret-value');

        $component = Livewire::actingAs($admin)->test(PushDataSiswasToServer::class)
            ->set('previewToken', '5a0ee1ab-6462-4c9c-a858-cfd35f8c2d0c')
            ->set('payloadChecksum', str_repeat('a', 64))
            ->call('applyPush');
        $this->assertSame(['counts' => []], $component->get('applyResult'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sync.example.test/api/internal/student-sync/apply'
            && $request->header('X-Student-Sync-Idempotency-Key')[0] === hash('sha256', '5a0ee1ab-6462-4c9c-a858-cfd35f8c2d0c|'.str_repeat('a', 64)));
    }

    private function student(int $id, string $status): void
    {
        DB::table('data_siswa')->insert([
            'id' => $id,
            'nama' => "Student {$id}",
            'nipd' => "NIPD-{$id}",
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function headerAction(ManageDataSiswas $page, string $name): mixed
    {
        foreach ($page->getCachedHeaderActions() as $action) {
            if ($action->getName() === $name) {
                return $action;
            }
        }

        $this->fail("Header action {$name} was not found.");
    }

    /** @param array<string, string> $levels */
    private function user(string $username, array $levels = []): User
    {
        return User::query()->create([
            'name' => $username,
            'username' => $username,
            'password' => bcrypt('password'),
            'module_access_levels' => $levels,
        ]);
    }
}
