<?php

namespace Tests\Feature;

use App\Filament\Resources\DataSiswaResource\Pages\ManageDataSiswas;
use App\Filament\Resources\DataSiswaResource\Pages\PushDataSiswasToServer;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
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
        $this->assertSame(['counts' => ['created' => 1], 'items' => []], $component->get('applyResult'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sync.example.test/api/internal/student-sync/apply'
            && $request->header('X-Student-Sync-Idempotency-Key')[0] === hash('sha256', '5a0ee1ab-6462-4c9c-a858-cfd35f8c2d0c|'.str_repeat('a', 64)));
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
