<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveiResource\Pages\CreateSurvei;
use App\Filament\Resources\SurveiResource\Pages\ListSurveis;
use App\Models\DataSiswa;
use App\Models\GuruTendik;
use App\Models\Survei;
use App\Models\SurveiQuestion;
use App\Models\SurveiSubmission;
use App\Models\SurveiTarget;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\Concerns\BootstrapsAdminFeatureTables;
use Tests\TestCase;

class SurveiFeatureTest extends TestCase
{
    use BootstrapsAdminFeatureTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapAdminFeatureTables();
        $this->runSurveiMigration();
    }

    public function test_admin_can_create_student_survey_and_sync_active_targets(): void
    {
        $admin = $this->makeAdminUser('admin-survei-siswa');

        $aktif = DataSiswa::query()->create([
            'nama' => 'Ananda Aktif',
            'rombel_saat_ini' => 'X-A',
            'status' => 'aktif',
        ]);

        $alumni = DataSiswa::query()->create([
            'nama' => 'Ananda Alumni',
            'rombel_saat_ini' => 'XII',
            'status' => 'alumni',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(CreateSurvei::class)
            ->set('data.title', 'Survei Kepuasan Ortu')
            ->set('data.audience_type', Survei::AUDIENCE_STUDENT)
            ->set('data.is_active', true)
            ->set('data.description', 'Mohon diisi oleh orang tua murid.')
            ->set('data.selected_student_ids', [(string) $aktif->getKey()])
            ->set('data.questions', [
                [
                    'prompt' => 'Bagaimana penilaian umum terhadap layanan sekolah?',
                    'question_type' => SurveiQuestion::TYPE_RATING,
                    'is_required' => true,
                    'options' => [],
                ],
                [
                    'prompt' => 'Apa masukan utama dari orang tua?',
                    'question_type' => SurveiQuestion::TYPE_LONG_TEXT,
                    'is_required' => false,
                    'options' => [],
                ],
            ])
            ->call('create')
            ->assertHasNoErrors();

        $survei = Survei::query()->firstOrFail();

        $this->assertSame('Survei Kepuasan Ortu', $survei->title);
        $this->assertSame(Survei::AUDIENCE_STUDENT, $survei->audience_type);
        $this->assertCount(2, $survei->questions);
        $this->assertDatabaseCount('survei_targets', 1);
        $this->assertDatabaseHas('survei_targets', [
            'survei_id' => $survei->getKey(),
            'data_siswa_id' => $aktif->getKey(),
            'submission_status' => SurveiTarget::STATUS_PENDING,
        ]);
        $this->assertDatabaseMissing('survei_targets', [
            'survei_id' => $survei->getKey(),
            'data_siswa_id' => $alumni->getKey(),
        ]);
    }

    public function test_admin_can_create_teacher_survey_for_guru_and_tendik_targets(): void
    {
        $admin = $this->makeAdminUser('admin-survei-guru');

        $guru = GuruTendik::query()->create([
            'nama' => 'Ustadz Hadi',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
        ]);

        $tendik = GuruTendik::query()->create([
            'nama' => 'Ibu Sari',
            'jenis_ptk' => 'Tendik',
            'status' => 'aktif',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(CreateSurvei::class)
            ->set('data.title', 'Survei Evaluasi Internal')
            ->set('data.audience_type', Survei::AUDIENCE_TEACHER)
            ->set('data.is_active', true)
            ->set('data.selected_guru_tendik_ids', [(string) $guru->getKey(), (string) $tendik->getKey()])
            ->set('data.questions', [
                [
                    'prompt' => 'Apakah alur kerja semester ini sudah efektif?',
                    'question_type' => SurveiQuestion::TYPE_SINGLE_CHOICE,
                    'is_required' => true,
                    'options' => [
                        ['label' => 'Ya'],
                        ['label' => 'Belum'],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoErrors();

        $survei = Survei::query()->where('title', 'Survei Evaluasi Internal')->firstOrFail();

        $this->assertDatabaseCount('survei_targets', 2);
        $this->assertDatabaseHas('survei_targets', [
            'survei_id' => $survei->getKey(),
            'guru_tendik_id' => $guru->getKey(),
        ]);
        $this->assertDatabaseHas('survei_targets', [
            'survei_id' => $survei->getKey(),
            'guru_tendik_id' => $tendik->getKey(),
        ]);
    }

    public function test_public_survey_link_can_be_filled_once_with_targeted_token(): void
    {
        $student = DataSiswa::query()->create([
            'nama' => 'Ananda Public',
            'rombel_saat_ini' => 'XI-B',
            'status' => 'aktif',
        ]);

        $survei = Survei::query()->create([
            'title' => 'Survei Kepuasan Wali Murid',
            'audience_type' => Survei::AUDIENCE_STUDENT,
            'is_active' => true,
        ]);

        $question = $survei->questions()->create([
            'urutan' => 1,
            'prompt' => 'Seberapa puas Anda dengan komunikasi sekolah?',
            'question_type' => SurveiQuestion::TYPE_RATING,
            'is_required' => true,
        ]);

        $target = $survei->targets()->create([
            'audience_type' => Survei::AUDIENCE_STUDENT,
            'data_siswa_id' => $student->getKey(),
            'recipient_name_snapshot' => $student->nama,
            'recipient_context_snapshot' => $student->rombel_saat_ini,
            'whatsapp_number' => '08123456789',
        ]);

        $this->get(route('survei.public.show', $target->access_token))
            ->assertOk()
            ->assertSee('Survei Kepuasan Wali Murid')
            ->assertSee('Seberapa puas Anda dengan komunikasi sekolah?')
            ->assertSee('Ananda Public');

        $this->post(route('survei.public.submit', $target->access_token), [
            'answers' => [
                $question->getKey() => 4,
            ],
        ])->assertRedirect(route('survei.public.show', $target->access_token));

        $this->assertDatabaseHas('survei_submissions', [
            'survei_id' => $survei->getKey(),
            'survei_target_id' => $target->getKey(),
        ]);

        $this->assertSame(SurveiTarget::STATUS_SUBMITTED, $target->fresh()->submission_status);

        $this->get(route('survei.public.show', $target->access_token))
            ->assertOk()
            ->assertSee('Survei sudah diisi.');
    }

    public function test_admin_list_page_shows_progress_summary_for_surveys(): void
    {
        $admin = $this->makeAdminUser('admin-survei-list');

        $survei = Survei::query()->create([
            'title' => 'Survei Disiplin',
            'audience_type' => Survei::AUDIENCE_STUDENT,
            'is_active' => true,
        ]);

        $targetA = $survei->targets()->create([
            'audience_type' => Survei::AUDIENCE_STUDENT,
            'recipient_name_snapshot' => 'Murid A',
            'recipient_context_snapshot' => 'X-A',
        ]);

        $targetB = $survei->targets()->create([
            'audience_type' => Survei::AUDIENCE_STUDENT,
            'recipient_name_snapshot' => 'Murid B',
            'recipient_context_snapshot' => 'X-B',
        ]);

        SurveiSubmission::query()->create([
            'survei_id' => $survei->getKey(),
            'survei_target_id' => $targetA->getKey(),
            'answers' => ['1' => 'Ya'],
            'submitted_at' => now(),
        ]);

        $targetA->markSubmitted();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(ListSurveis::class)
            ->call('loadTable')
            ->assertSee('Survei Disiplin')
            ->assertSee('1 / 2')
            ->assertSee('Belum: 1')
            ->assertSee('Murid / Orang Tua');
    }

    protected function runSurveiMigration(): void
    {
        $migration = require database_path('migrations/2026_04_07_090000_create_survei_tables.php');
        $migration->up();
    }

    protected function makeAdminUser(string $username): User
    {
        $user = User::query()->create([
            'name' => 'Admin Survei',
            'username' => $username,
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('admin');

        return $user;
    }
}
