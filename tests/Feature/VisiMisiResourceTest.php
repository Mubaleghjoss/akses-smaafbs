<?php

namespace Tests\Feature;

use App\Filament\Resources\VisiMisiResource;
use App\Filament\Resources\VisiMisiResource\Pages\CreateVisiMisi;
use App\Filament\Resources\VisiMisiResource\Pages\EditVisiMisi;
use App\Models\User;
use App\Models\VisiMisi;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class VisiMisiResourceTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        $this->ensureVisiMisiTable();
    }

    public function test_admin_can_create_and_update_singleton_visi_misi_from_resource_flow(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Visi Misi',
            'username' => 'admin-visi-misi',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(CreateVisiMisi::class)
            ->set('data.title', '  <h1>Visi Sekolah</h1>  ')
            ->set('data.content', '<p>Membentuk generasi unggul.</p><script>alert("x")</script>')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('visi_misis', 1);
        $record = VisiMisi::query()->firstOrFail();

        $this->assertSame('Visi Sekolah', $record->title);
        $this->assertStringContainsString('Membentuk generasi unggul.', $record->content);
        $this->assertStringNotContainsString('<script', $record->content);
        $this->assertFalse(VisiMisiResource::canCreate());

        Livewire::actingAs($admin)
            ->test(EditVisiMisi::class, ['record' => $record->getKey()])
            ->set('data.title', 'Misi Sekolah Terbaru')
            ->set('data.content', '<p>Menguatkan iman, ilmu, dan kepemimpinan.</p>')
            ->call('save')
            ->assertHasNoErrors();

        $record->refresh();

        $this->assertSame('Misi Sekolah Terbaru', $record->title);
        $this->assertStringContainsString('Menguatkan iman, ilmu, dan kepemimpinan.', $record->content);
        $this->assertDatabaseCount('visi_misis', 1);
    }

    public function test_singleton_key_enforces_single_record_boundary(): void
    {
        VisiMisi::query()->create([
            'title' => 'Visi Awal',
            'content' => '<p>Konten awal.</p>',
        ]);

        $this->expectException(QueryException::class);

        VisiMisi::query()->create([
            'title' => 'Visi Kedua',
            'content' => '<p>Konten kedua.</p>',
        ]);
    }

    protected function ensureVisiMisiTable(): void
    {
        if (Schema::hasTable('visi_misis')) {
            return;
        }

        Schema::create('visi_misis', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('singleton_key')->default(1)->unique();
            $table->string('title', 160);
            $table->longText('content');
            $table->timestamps();
        });
    }
}
