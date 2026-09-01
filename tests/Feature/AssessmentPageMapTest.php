<?php

namespace Tests\Feature;

use App\Enums\Assessment\AssessmentType;
use App\Filament\Pages\Assessment\AsasHub;
use App\Filament\Pages\Assessment\AsatHomeroomRecap;
use App\Filament\Pages\Assessment\AsatHub;
use App\Filament\Pages\Assessment\AsatInputScores;
use App\Filament\Pages\Assessment\AsatReports;
use App\Filament\Pages\Assessment\AsatSubmissionStatus;
use App\Filament\Pages\Assessment\AssessmentDashboard;
use App\Filament\Pages\Assessment\AssessmentSetupWizard;
use App\Filament\Pages\Assessment\AssessmentTeachingMatrix;
use App\Filament\Pages\Assessment\AstsHub;
use App\Support\Admin\AdminModuleAccess;
use App\Support\Admin\AdminSchoolNavigation;
use App\Support\Assessment\AssessmentPageMap;
use Tests\TestCase;

/**
 * Menjaga agar penambahan JENIS penilaian tidak lagi menyisakan halaman yang
 * menumpang jenis lain.
 *
 * Sebelum peta halaman dipusatkan, tautan dibangun dengan pola dua cabang
 * (`$type === ASTS ? A : B`). Akibatnya ASAT — jenis ketiga — selalu diarahkan
 * ke halaman ASAS tanpa pesan kesalahan: guru merasa membuka ASAT, tetapi
 * mengisi layar ASAS. Test ini gagal apabila ada jenis pada enum yang tidak
 * memiliki halaman sendiri, atau ada jenis yang tidak muncul di menu.
 */
class AssessmentPageMapTest extends TestCase
{
    public function test_setiap_jenis_penilaian_punya_halaman_sendiri_untuk_semua_bagian(): void
    {
        $peta = AssessmentPageMap::all();

        foreach (AssessmentType::cases() as $type) {
            $this->assertArrayHasKey(
                $type->value,
                $peta,
                "Jenis {$type->label()} belum dipetakan ke halaman mana pun.",
            );

            foreach (AssessmentPageMap::SECTIONS as $section) {
                $this->assertArrayHasKey(
                    $section,
                    $peta[$type->value],
                    "Jenis {$type->label()} belum punya halaman bagian '{$section}'.",
                );
                $this->assertTrue(
                    class_exists($peta[$type->value][$section]),
                    "Kelas halaman {$peta[$type->value][$section]} tidak ditemukan.",
                );
            }
        }
    }

    public function test_tidak_ada_halaman_yang_dipakai_dua_jenis_sekaligus(): void
    {
        $semuaKelas = collect(AssessmentPageMap::all())
            ->flatMap(fn (array $pages): array => array_values($pages));

        $this->assertSame(
            $semuaKelas->count(),
            $semuaKelas->unique()->count(),
            'Ada halaman yang dipakai oleh lebih dari satu jenis penilaian; '
                .'jenis tersebut akan saling menimpa saat dibuka.',
        );
    }

    public function test_asat_memakai_halamannya_sendiri_bukan_halaman_asas(): void
    {
        $this->assertSame(AsatHub::class, AssessmentPageMap::page(AssessmentType::ASAT, 'hub'));
        $this->assertSame(AsatInputScores::class, AssessmentPageMap::page(AssessmentType::ASAT, 'input'));
        $this->assertSame(AsatSubmissionStatus::class, AssessmentPageMap::page(AssessmentType::ASAT, 'status'));
        $this->assertSame(AsatHomeroomRecap::class, AssessmentPageMap::page(AssessmentType::ASAT, 'recap'));
        $this->assertSame(AsatReports::class, AssessmentPageMap::page(AssessmentType::ASAT, 'reports'));
    }

    public function test_type_dari_kolom_string_dikenali_dan_nilai_asing_tidak_meledak(): void
    {
        $this->assertSame(AssessmentType::ASAT, AssessmentPageMap::normalizeType('asat'));
        $this->assertSame(AssessmentType::ASAT, AssessmentPageMap::normalizeType(AssessmentType::ASAT));
        $this->assertNull(AssessmentPageMap::normalizeType(null));
        $this->assertNull(AssessmentPageMap::normalizeType('jenis-yang-tidak-ada'));

        // Jenis tak dikenal jatuh ke ASTS (jenis paling dasar), bukan ke ASAS.
        $this->assertSame(
            AstsHub::class,
            AssessmentPageMap::page(AssessmentPageMap::normalizeType('jenis-yang-tidak-ada'), 'hub'),
        );
    }

    public function test_bagian_halaman_dapat_dikenali_dari_kelasnya(): void
    {
        $this->assertSame('hub', AssessmentPageMap::sectionOf(AsatHub::class));
        $this->assertSame('reports', AssessmentPageMap::sectionOf(AsatReports::class));
        $this->assertNull(AssessmentPageMap::sectionOf(AssessmentDashboard::class));
    }

    public function test_menu_penilaian_memuat_setiap_jenis_dan_halaman_pengaturan_utama(): void
    {
        foreach (AssessmentType::cases() as $type) {
            $hub = AssessmentPageMap::page($type, 'hub');

            $this->assertTrue(
                AdminSchoolNavigation::shouldRegisterAssessmentClass($hub),
                "Pusat {$type->label()} tidak terdaftar di menu, sehingga jenis ini tidak dapat ditemukan pengguna.",
            );
            $this->assertSame(
                'Penilaian',
                AdminSchoolNavigation::parentItemForClass($hub),
                "Pusat {$type->label()} tidak berada di bawah induk menu Penilaian.",
            );
            $this->assertSame(
                AdminSchoolNavigation::GROUP,
                AdminSchoolNavigation::effectiveGroupForClass($hub),
            );
        }

        foreach ([AssessmentSetupWizard::class, AssessmentTeachingMatrix::class, AssessmentDashboard::class] as $class) {
            $this->assertTrue(AdminSchoolNavigation::shouldRegisterAssessmentClass($class));
            $this->assertSame('Penilaian', AdminSchoolNavigation::parentItemForClass($class));
        }
    }

    public function test_halaman_turunan_tidak_menambah_baris_sidebar(): void
    {
        foreach (AssessmentPageMap::all() as $pages) {
            foreach ($pages as $section => $class) {
                if ($section === 'hub') {
                    continue;
                }

                $this->assertFalse(
                    AdminSchoolNavigation::shouldRegisterAssessmentClass($class),
                    "Halaman {$class} seharusnya dibuka dari pusat jenis, bukan menjadi baris sidebar sendiri.",
                );
            }
        }
    }

    public function test_satu_izin_modul_penilaian_membuka_seluruh_halaman_setiap_jenis(): void
    {
        $classes = AdminModuleAccess::itemClassesForLevels([
            'penilaian' => AdminModuleAccess::VIEW,
        ]);

        foreach (AssessmentPageMap::all() as $pages) {
            foreach ($pages as $class) {
                $this->assertContains(
                    $class,
                    $classes,
                    "Halaman {$class} tidak ikut terbuka oleh izin modul 'penilaian', "
                        .'sehingga menu tampak ada tetapi isinya tertutup.',
                );
            }
        }

        foreach ([AssessmentSetupWizard::class, AssessmentTeachingMatrix::class, AsasHub::class] as $class) {
            $this->assertContains($class, $classes);
        }
    }
}
