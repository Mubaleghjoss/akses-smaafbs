<?php

namespace Tests\Feature;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\AssignmentStatus;
use App\Filament\Pages\Assessment\AssessmentReportProgressPage;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\User;
use App\Support\Admin\AdminModuleAccess;
use App\Support\Admin\AdminSchoolNavigation;
use App\Support\Assessment\AssessmentReportProgress;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\BootstrapsStudentAndTeacherTables;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class AssessmentReportProgressTest extends TestCase
{
    use BootstrapsStudentAndTeacherTables;
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        config(['assessment.enabled' => true]);
        $this->bootstrapStudentAndTeacherTables();
        $this->bootstrapUserAndPermissionTables();
        (require database_path('migrations/2026_07_31_080000_create_assessment_foundation_tables.php'))->up();
        (require database_path('migrations/2026_07_31_120000_extend_assessment_report_structure.php'))->up();
        (require database_path('migrations/2026_08_06_150000_add_assessment_subject_categories.php'))->up();
    }

    public function test_semua_periode_operasional_tampil_dan_periode_draf_atau_terbit_tidak_tampil(): void
    {
        $user = User::factory()->create(['username' => 'penguji-progres']);
        Role::findOrCreate('super_admin', 'web');
        $user->assignRole('super_admin');

        foreach ([
            AssessmentPeriodStatus::OPEN,
            AssessmentPeriodStatus::ENTRY_CLOSED,
            AssessmentPeriodStatus::VERIFICATION,
            AssessmentPeriodStatus::LOCKED,
        ] as $index => $status) {
            AssessmentPeriod::factory()->create([
                'code' => 'AKTIF-'.$index,
                'name' => 'Periode '.$status->label(),
                'type' => AssessmentType::cases()[$index % 3],
                'status' => $status,
            ]);
        }

        AssessmentPeriod::factory()->create(['code' => 'DRAF-X', 'status' => AssessmentPeriodStatus::DRAFT]);
        AssessmentPeriod::factory()->create(['code' => 'TERBIT-X', 'status' => AssessmentPeriodStatus::PUBLISHED]);

        $periods = app(AssessmentReportProgress::class)->forUser($user);

        $this->assertCount(4, $periods);
        $this->assertEqualsCanonicalizing(
            ['AKTIF-0', 'AKTIF-1', 'AKTIF-2', 'AKTIF-3'],
            $periods->pluck('code')->all(),
        );
    }

    public function test_akun_rangkap_melihat_kelompok_guru_dan_wali_dari_tanggung_jawab_nyata(): void
    {
        $user = User::factory()->create([
            'username' => 'guru-walas-progres',
            'guru_tendik_id' => 991,
        ]);
        $period = AssessmentPeriod::factory()->asas()->create([
            'code' => 'ASAS-AKTIF',
            'name' => 'ASAS Ganjil',
            'status' => AssessmentPeriodStatus::OPEN,
        ]);
        $rombel = AssessmentPeriodRombel::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'rombel_name_snapshot' => 'XI IPA 1',
        ]);
        AssessmentPeriodAssignment::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'teacher_id' => 991,
            'status' => AssignmentStatus::RETURNED,
        ]);
        AssessmentPeriodAssignment::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'teacher_id' => 991,
            'status' => AssignmentStatus::SUBMITTED,
        ]);
        AssessmentPeriodHomeroom::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'teacher_id' => 991,
            'rombel_name_snapshot' => 'XI IPA 1',
        ]);

        $periodProgress = app(AssessmentReportProgress::class)->forUser($user)->firstWhere('code', 'ASAS-AKTIF');

        $this->assertNotNull($periodProgress);
        $this->assertSame(['teacher', 'homeroom'], collect($periodProgress['roles'])->pluck('key')->all());
        $teacher = collect($periodProgress['roles'])->firstWhere('key', 'teacher');
        $this->assertSame(2, $teacher['total']);
        $this->assertSame(1, $teacher['completed']);
        $this->assertSame(50, $teacher['percent']);
        $this->assertStringContainsString('Perbaiki', $teacher['next_action']);
        $this->assertStringContainsString('period='.$period->getKey(), $teacher['url']);
        $homeroom = collect($periodProgress['roles'])->firstWhere('key', 'homeroom');
        $this->assertSame('XI IPA 1', $homeroom['scope']);
        $this->assertStringContainsString('Lengkapi', $homeroom['next_action']);
    }

    public function test_guru_tidak_melihat_periode_aktif_di_luar_tanggung_jawabnya(): void
    {
        $user = User::factory()->create([
            'username' => 'guru-scope-progres',
            'guru_tendik_id' => 992,
        ]);
        $owned = AssessmentPeriod::factory()->asts()->create([
            'code' => 'ASTS-MILIK-GURU',
            'status' => AssessmentPeriodStatus::OPEN,
        ]);
        $ownedRombel = AssessmentPeriodRombel::factory()->create(['assessment_period_id' => $owned->getKey()]);
        AssessmentPeriodAssignment::factory()->create([
            'assessment_period_id' => $owned->getKey(),
            'assessment_period_rombel_id' => $ownedRombel->getKey(),
            'teacher_id' => 992,
        ]);
        AssessmentPeriod::factory()->asas()->create([
            'code' => 'ASAS-BUKAN-MILIK-GURU',
            'status' => AssessmentPeriodStatus::OPEN,
        ]);

        $codes = app(AssessmentReportProgress::class)->forUser($user)->pluck('code')->all();

        $this->assertSame(['ASTS-MILIK-GURU'], $codes);
    }

    public function test_kurikulum_melihat_status_seluruh_assignment_dan_tugas_verifikasi(): void
    {
        $user = User::factory()->create(['username' => 'kurikulum-progres']);
        Role::findOrCreate('kurikulum', 'web');
        $user->assignRole('kurikulum');
        $period = AssessmentPeriod::factory()->asts()->create([
            'code' => 'ASTS-VERIFIKASI',
            'status' => AssessmentPeriodStatus::VERIFICATION,
        ]);
        $rombel = AssessmentPeriodRombel::factory()->create(['assessment_period_id' => $period->getKey()]);
        foreach ([AssignmentStatus::SUBMITTED, AssignmentStatus::VERIFIED, AssignmentStatus::DRAFT] as $status) {
            AssessmentPeriodAssignment::factory()->create([
                'assessment_period_id' => $period->getKey(),
                'assessment_period_rombel_id' => $rombel->getKey(),
                'status' => $status,
            ]);
        }

        $progress = app(AssessmentReportProgress::class)->forUser($user)->firstWhere('code', 'ASTS-VERIFIKASI');
        $curriculum = collect($progress['roles'])->firstWhere('key', 'curriculum');

        $this->assertNotNull($curriculum);
        $this->assertSame(3, $curriculum['total']);
        $this->assertSame(1, $curriculum['completed']);
        $this->assertSame(1, $curriculum['waiting_verification']);
        $this->assertStringContainsString('Verifikasi', $curriculum['next_action']);
        $this->assertStringContainsString('period='.$period->getKey(), $curriculum['url']);
    }

    public function test_admin_tetap_fail_closed_bila_template_atau_preflight_belum_siap(): void
    {
        $admin = User::factory()->create(['username' => 'admin-progres']);
        Role::findOrCreate('super_admin', 'web');
        $admin->assignRole('super_admin');
        $period = AssessmentPeriod::factory()->asas()->create([
            'code' => 'ASAS-TANPA-TEMPLATE',
            'status' => AssessmentPeriodStatus::LOCKED,
        ]);

        $progress = app(AssessmentReportProgress::class)->forUser($admin)->firstWhere('code', 'ASAS-TANPA-TEMPLATE');
        $adminRole = collect($progress['roles'])->firstWhere('key', 'admin');

        $this->assertNotNull($adminRole);
        $this->assertSame('Belum Siap Cetak', $progress['readiness_label']);
        $this->assertFalse($progress['ready_to_print']);
        $this->assertSame(0, $progress['overall_percent']);
        $this->assertStringContainsString('template', mb_strtolower($adminRole['next_action']));
        $this->assertStringContainsString('period='.$period->getKey(), $adminRole['url']);
    }

    public function test_halaman_progres_rapor_menampilkan_semua_periode_dan_kelompok_tanggung_jawab(): void
    {
        $admin = User::factory()->create(['username' => 'admin-halaman-progres']);
        Role::findOrCreate('super_admin', 'web');
        $admin->assignRole('super_admin');
        AssessmentPeriod::factory()->asts()->create([
            'code' => 'ASTS-HALAMAN',
            'name' => 'ASTS Aktif Halaman',
            'status' => AssessmentPeriodStatus::OPEN,
        ]);
        AssessmentPeriod::factory()->asas()->create([
            'code' => 'ASAS-HALAMAN',
            'name' => 'ASAS Aktif Halaman',
            'status' => AssessmentPeriodStatus::VERIFICATION,
        ]);

        $this->actingAs($admin);

        Livewire::test(AssessmentReportProgressPage::class)
            ->assertSee('Progres Rapor')
            ->assertSee('ASTS Aktif Halaman')
            ->assertSee('ASAS Aktif Halaman')
            ->assertSee('Sebagai Admin')
            ->assertSee('Belum Siap Cetak')
            ->assertSeeHtml('assessment-report-progress');

        $this->assertSame('Penilaian', AdminSchoolNavigation::parentItemForClass(AssessmentReportProgressPage::class));
        $this->assertTrue(AdminSchoolNavigation::shouldRegisterAssessmentClass(AssessmentReportProgressPage::class));
    }

    public function test_empty_state_menjelaskan_periode_masih_disusun_dan_belum_waktunya(): void
    {
        $user = User::factory()->create(['username' => 'guru-belum-waktunya']);
        AssessmentPeriod::factory()->asts()->create([
            'name' => 'ASTS Ganjil Mendatang',
            'status' => AssessmentPeriodStatus::DRAFT,
            'entry_start_at' => now()->addDays(7),
        ]);

        $state = app(AssessmentReportProgress::class)->emptyStateForUser($user);

        $this->assertSame('not_started', $state['key']);
        $this->assertSame('Progres Rapor Belum Dibuka', $state['title']);
        $this->assertStringContainsString('ASTS Ganjil Mendatang', $state['description']);
        $this->assertStringContainsString('belum perlu', mb_strtolower($state['action']));
    }

    public function test_empty_state_menjelaskan_periode_aktif_tetapi_akun_belum_punya_tanggung_jawab(): void
    {
        $user = User::factory()->create(['username' => 'guru-belum-ditugaskan']);
        $user->forceFill([
            'module_access_levels' => ['penilaian' => AdminModuleAccess::VIEW],
        ])->save();
        AssessmentPeriod::factory()->asas()->create([
            'name' => 'ASAS Ganjil Aktif',
            'status' => AssessmentPeriodStatus::OPEN,
        ]);

        $state = app(AssessmentReportProgress::class)->emptyStateForUser($user);

        $this->assertSame('unassigned', $state['key']);
        $this->assertSame('Belum Ada Tanggung Jawab Rapor', $state['title']);
        $this->assertStringContainsString('ASAS Ganjil Aktif', $state['description']);
        $this->assertStringContainsString('Kurikulum atau Admin', $state['action']);

        $this->actingAs($user);

        Livewire::test(AssessmentReportProgressPage::class)
            ->assertSee('Belum Ada Tanggung Jawab Rapor')
            ->assertSee('ASAS Ganjil Aktif')
            ->assertSee('hubungi Kurikulum atau Admin')
            ->assertSeeHtml('assessment-report-progress__empty-state');
    }

    public function test_css_struktural_progres_rapor_tidak_bergantung_pada_utility_build(): void
    {
        $css = file_get_contents(public_path('css/filament-admin-responsive.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('.assessment-report-progress { display:flex; flex-direction:column; gap:1.5rem; }', $css);
        $this->assertStringContainsString('.assessment-report-progress__hero,.assessment-report-progress__period,.assessment-report-progress__empty-state { border-style:solid; border-width:1px; border-radius:1rem; padding:1.5rem; }', $css);
        $this->assertStringContainsString('.assessment-report-progress__path { display:grid; grid-template-columns:repeat(8,minmax(0,1fr)); gap:.5rem; }', $css);
        $this->assertStringContainsString('.assessment-report-progress__roles { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; }', $css);
        $this->assertStringContainsString('.assessment-report-progress__action { display:inline-flex; min-height:2.5rem; align-items:center; justify-content:center; }', $css);
    }
}
