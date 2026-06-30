<?php

namespace Tests\Feature;

use App\Filament\Resources\CalendarEventResource\Pages\CreateCalendarEvent;
use App\Models\CalendarEvent;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Feature\Concerns\BootstrapsAdminPanel;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class CalendarEventCreatePageTest extends TestCase
{
    use BootstrapsAdminPanel;
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        $this->bootstrapAdminPanel();
        $this->ensureCalendarEventsTable();

        CalendarEvent::query()->delete();
    }

    public function test_admin_can_create_multiple_calendar_events_with_visibility(): void
    {
        $admin = $this->createAdminUser();

        Livewire::actingAs($admin)
            ->test(CreateCalendarEvent::class)
            ->call('createEvents', [
                'titles' => ['Apel pagi', 'Rapat guru'],
                'description' => 'Agenda internal sekolah',
                'start' => '2026-04-20',
                'end' => '2026-04-22',
                'visibility' => 'internal',
            ])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('calendar_events', [
            'title' => 'Apel pagi',
            'description' => 'Agenda internal sekolah',
            'visibility' => 'internal',
        ]);

        $this->assertDatabaseHas('calendar_events', [
            'title' => 'Rapat guru',
            'description' => 'Agenda internal sekolah',
            'visibility' => 'internal',
        ]);
    }

    public function test_admin_can_import_numbered_calendar_events(): void
    {
        $admin = $this->createAdminUser();

        Livewire::actingAs($admin)
            ->test(CreateCalendarEvent::class)
            ->set('importVisibility', 'internal')
            ->set('importText', "Agenda Kegiatan Mei 2026\n5 Mei 2026\n1. KBM semester 2\n2. Apel pagi")
            ->call('importFromText')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('calendar_events', [
            'title' => 'KBM semester 2',
            'visibility' => 'internal',
        ]);

        $this->assertDatabaseHas('calendar_events', [
            'title' => 'Apel pagi',
            'visibility' => 'internal',
        ]);
    }

    public function test_new_calendar_event_replaces_existing_event_on_same_schedule_and_visibility(): void
    {
        $admin = $this->createAdminUser();

        $existingPublic = CalendarEvent::query()->create([
            'title' => 'Agenda lama',
            'description' => 'Data lama',
            'visibility' => 'external',
            'all_day' => true,
            'start' => '2026-04-20 00:00:00',
            'end' => null,
        ]);

        $existingInternal = CalendarEvent::query()->create([
            'title' => 'Agenda internal lama',
            'description' => 'Tetap tersimpan',
            'visibility' => 'internal',
            'all_day' => true,
            'start' => '2026-04-20 00:00:00',
            'end' => null,
        ]);

        Livewire::actingAs($admin)
            ->test(CreateCalendarEvent::class)
            ->call('createEvent', [
                'title' => 'Agenda pengganti',
                'description' => 'Data baru',
                'start' => '2026-04-20',
                'end' => null,
                'visibility' => 'external',
            ])
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('calendar_events', [
            'id' => $existingPublic->id,
        ]);

        $this->assertDatabaseHas('calendar_events', [
            'title' => 'Agenda pengganti',
            'description' => 'Data baru',
            'visibility' => 'external',
        ]);

        $this->assertDatabaseHas('calendar_events', [
            'id' => $existingInternal->id,
            'title' => 'Agenda internal lama',
            'visibility' => 'internal',
        ]);

        $this->assertSame(
            1,
            CalendarEvent::query()
                ->where('visibility', 'external')
                ->whereDate('start', '2026-04-20')
                ->count()
        );
    }

    public function test_import_replaces_existing_events_for_imported_dates_without_dropping_new_items(): void
    {
        $admin = $this->createAdminUser();

        $singleDay = CalendarEvent::query()->create([
            'title' => 'Agenda publik lama',
            'description' => null,
            'visibility' => 'external',
            'all_day' => true,
            'start' => '2026-05-05 00:00:00',
            'end' => null,
        ]);

        $dateRange = CalendarEvent::query()->create([
            'title' => 'Rentang lama',
            'description' => null,
            'visibility' => 'external',
            'all_day' => true,
            'start' => '2026-05-05 00:00:00',
            'end' => '2026-05-06 00:00:00',
        ]);

        Livewire::actingAs($admin)
            ->test(CreateCalendarEvent::class)
            ->set('importVisibility', 'external')
            ->set('importText', "Agenda Kegiatan Mei 2026\n5 Mei 2026\n1. KBM semester 2\n2. Apel pagi")
            ->call('importFromText')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('calendar_events', [
            'id' => $singleDay->id,
        ]);

        $this->assertDatabaseMissing('calendar_events', [
            'id' => $dateRange->id,
        ]);

        $this->assertDatabaseHas('calendar_events', [
            'title' => 'KBM semester 2',
            'visibility' => 'external',
        ]);

        $this->assertDatabaseHas('calendar_events', [
            'title' => 'Apel pagi',
            'visibility' => 'external',
        ]);

        $this->assertSame(
            2,
            CalendarEvent::query()
                ->where('visibility', 'external')
                ->whereDate('start', '2026-05-05')
                ->count()
        );
    }

    public function test_admin_can_update_calendar_event_visibility_and_range(): void
    {
        $admin = $this->createAdminUser();

        $event = CalendarEvent::query()->create([
            'title' => 'Workshop siswa',
            'description' => null,
            'visibility' => 'external',
            'all_day' => true,
            'start' => '2026-04-21 00:00:00',
            'end' => null,
        ]);

        Livewire::actingAs($admin)
            ->test(CreateCalendarEvent::class)
            ->call('updateEvent', $event->id, [
                'title' => 'Workshop siswa dan guru',
                'description' => 'Diperbarui dari halaman kalender',
                'start' => '2026-04-21',
                'end' => '2026-04-23',
                'visibility' => 'internal',
            ])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('calendar_events', [
            'id' => $event->id,
            'title' => 'Workshop siswa dan guru',
            'description' => 'Diperbarui dari halaman kalender',
            'visibility' => 'internal',
        ]);
    }

    protected function createAdminUser(): User
    {
        $user = User::query()->create([
            'name' => 'Admin Kalender',
            'username' => 'admin-kalender',
            'password' => bcrypt('password'),
        ]);

        $user->assignRole('admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $user;
    }

    protected function ensureCalendarEventsTable(): void
    {
        if (Schema::hasTable('calendar_events')) {
            return;
        }

        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('visibility')->nullable();
            $table->boolean('all_day')->default(true);
            $table->dateTime('start')->nullable();
            $table->dateTime('end')->nullable();
            $table->timestamps();
        });
    }
}
