<?php

namespace App\Support\Admin;

use App\Filament\Pages\Assessment\AsasHomeroomRecap;
use App\Filament\Pages\Assessment\AsasHub;
use App\Filament\Pages\Assessment\AsasInputScores;
use App\Filament\Pages\Assessment\AsasReports;
use App\Filament\Pages\Assessment\AsasSubmissionStatus;
use App\Filament\Pages\Assessment\AssessmentDashboard;
use App\Filament\Pages\Assessment\AssessmentMasterImport;
use App\Filament\Pages\Assessment\AstsHomeroomRecap;
use App\Filament\Pages\Assessment\AstsHub;
use App\Filament\Pages\Assessment\AstsInputScores;
use App\Filament\Pages\Assessment\AstsReports;
use App\Filament\Pages\Assessment\AstsSubmissionStatus;
use App\Filament\Pages\DashboardProker;
use App\Filament\Pages\SarprasStickerSettings;
use App\Filament\Resources\AssessmentAuditLogResource;
use App\Filament\Resources\AssessmentPeriodResource;
use App\Filament\Resources\AssessmentReportTemplateResource;
use App\Filament\Resources\AssessmentSchemeResource;
use App\Filament\Resources\AssessmentSubjectResource;
use App\Filament\Resources\BeritaResource;
use App\Filament\Resources\BerkasGuruResource;
use App\Filament\Resources\BerkasSiswaResource;
use App\Filament\Resources\BoardingArsipMtResource;
use App\Filament\Resources\BoardingKeuanganSiswaResource;
use App\Filament\Resources\BoardingKonselingMtResource;
use App\Filament\Resources\BoardingPencapaianResource;
use App\Filament\Resources\BoardingPerizinanSiswaResource;
use App\Filament\Resources\BoardingRapotResource;
use App\Filament\Resources\CalendarEventResource;
use App\Filament\Resources\CatatanBkResource;
use App\Filament\Resources\DataSiswaResource;
use App\Filament\Resources\DokumenKomiteResource;
use App\Filament\Resources\EventTimelineResource;
use App\Filament\Resources\GaleriResource;
use App\Filament\Resources\GuruTendikResource;
use App\Filament\Resources\JenisBerkasResource;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use App\Filament\Resources\PrestasiResource;
use App\Filament\Resources\ProfilSekolahResource;
use App\Filament\Resources\ProkerBidangResource;
use App\Filament\Resources\ProkerResource;
use App\Filament\Resources\RombelResource;
use App\Filament\Resources\SarprasActivityResource;
use App\Filament\Resources\SarprasBospInventoryResource;
use App\Filament\Resources\SarprasMonthlyAgendaResource;
use App\Filament\Resources\SarprasRoomInventoryResource;
use App\Filament\Resources\StrukturKomiteResource;
use App\Filament\Resources\StrukturOrganisasiResource;
use App\Filament\Resources\SurveiResource;
use App\Filament\Resources\UksRecordResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\VisiMisiResource;
use App\Models\User;
use Filament\Pages\Dashboard;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class AdminModuleAccess
{
    public const NONE = 'none';

    public const VIEW = 'view';

    public const MANAGE = 'manage';

    /**
     * @var array<string, class-string>
     */
    protected const RESOURCE_MAP = [
        'users' => UserResource::class,
        'data_siswa' => DataSiswaResource::class,
        'rombel' => RombelResource::class,
        'jenis_berkas' => JenisBerkasResource::class,
        'berkas_siswa' => BerkasSiswaResource::class,
        'guru_tendik' => GuruTendikResource::class,
        'berkas_guru' => BerkasGuruResource::class,
        'prestasi' => PrestasiResource::class,
        'uks_records' => UksRecordResource::class,
        'boarding_rapot' => BoardingRapotResource::class,
        'boarding_pencapaian' => BoardingPencapaianResource::class,
        'boarding_konseling' => BoardingKonselingMtResource::class,
        'boarding_keuangan' => BoardingKeuanganSiswaResource::class,
        'boarding_arsip' => BoardingArsipMtResource::class,
        'boarding_perizinan' => BoardingPerizinanSiswaResource::class,
        'catatan_bk' => CatatanBkResource::class,
        'survei' => SurveiResource::class,
        'proker_dashboard' => DashboardProker::class,
        'proker_bidang' => ProkerBidangResource::class,
        'proker' => ProkerResource::class,
        'profil_sekolah' => ProfilSekolahResource::class,
        'struktur_organisasi' => StrukturOrganisasiResource::class,
        'struktur_komite' => StrukturKomiteResource::class,
        'dokumen_komite' => DokumenKomiteResource::class,
        'visi_misi' => VisiMisiResource::class,
        'calendar_events' => CalendarEventResource::class,
        'event_timelines' => EventTimelineResource::class,
        'berita' => BeritaResource::class,
        'galeri' => GaleriResource::class,
        'perpustakaan_literasi' => PerpustakaanLiterasiMaterialResource::class,
        'sarpras_bosp_inventory' => SarprasBospInventoryResource::class,
        'sarpras_sticker_settings' => SarprasStickerSettings::class,
        'sarpras_room_inventory' => SarprasRoomInventoryResource::class,
        'sarpras_activity' => SarprasActivityResource::class,
        'sarpras_monthly_agenda' => SarprasMonthlyAgendaResource::class,
        'penilaian' => AssessmentDashboard::class,
    ];

    /**
     * Satu pengaturan akses modul Penilaian membuka seluruh halaman di dalam
     * grupnya. Policy record dan permission granular tetap menjadi pagar aksi.
     *
     * @var array<string, array<int, class-string>>
     */
    protected const ADDITIONAL_MODULE_CLASSES = [
        'penilaian' => [
            AstsHub::class,
            AsasHub::class,
            AstsInputScores::class,
            AstsSubmissionStatus::class,
            AstsHomeroomRecap::class,
            AstsReports::class,
            AsasInputScores::class,
            AsasSubmissionStatus::class,
            AsasHomeroomRecap::class,
            AsasReports::class,
            AssessmentPeriodResource::class,
            AssessmentSchemeResource::class,
            AssessmentSubjectResource::class,
            AssessmentReportTemplateResource::class,
            AssessmentAuditLogResource::class,
            AssessmentMasterImport::class,
        ],
    ];

    /**
     * @var array<string, string>
     */
    protected const MODULE_DESCRIPTIONS = [
        'users' => 'Kelola akun admin dan pengaturan akses pengguna.',
        'data_siswa' => 'Lihat atau kelola data induk siswa.',
        'rombel' => 'Kelola master rombel yang dipakai pada data siswa dan filter siswa.',
        'jenis_berkas' => 'Atur master jenis berkas siswa/guru.',
        'berkas_siswa' => 'Akses dokumen dan berkas siswa.',
        'guru_tendik' => 'Lihat atau kelola data profil guru dan tendik.',
        'berkas_guru' => 'Akses dokumen dan berkas guru.',
        'prestasi' => 'Lihat atau kelola data prestasi siswa.',
        'uks_records' => 'Akses rekam kunjungan dan layanan UKS.',
        'boarding_rapot' => 'Akses rapot boarding dan dokumen turunannya.',
        'boarding_pencapaian' => 'Kelola target dan capaian boarding.',
        'boarding_konseling' => 'Kelola catatan konseling boarding.',
        'boarding_keuangan' => 'Kelola keuangan siswa boarding.',
        'boarding_arsip' => 'Akses arsip dan dokumen boarding.',
        'boarding_perizinan' => 'Kelola perizinan keluar dan data kepulangan siswa boarding.',
        'catatan_bk' => 'Kelola catatan bimbingan konseling siswa.',
        'survei' => 'Kelola survei sekolah, target responden, dan monitoring hasil pengisian.',
        'proker_dashboard' => 'Lihat dashboard ringkasan proker dan aksi cepat monitoring.',
        'proker_bidang' => 'Kelola master bidang proker dan penanggung jawabnya.',
        'proker' => 'Kelola daftar program kerja, indikator, dan monitoring pelaksanaannya.',
        'profil_sekolah' => 'Kelola identitas resmi sekolah untuk halaman publik dan dokumen internal.',
        'struktur_organisasi' => 'Kelola struktur sekolah dan jabatan internal.',
        'struktur_komite' => 'Kelola struktur komite sekolah per periode.',
        'dokumen_komite' => 'Kelola arsip dokumen dan dokumentasi kegiatan komite.',
        'visi_misi' => 'Kelola dokumen visi misi sekolah yang tampil di frontend.',
        'calendar_events' => 'Kelola agenda kalender sekolah yang tampil di admin dan publik.',
        'event_timelines' => 'Kelola timeline kegiatan dan tahapan acara.',
        'berita' => 'Kelola berita sekolah dan update perkembangannya.',
        'galeri' => 'Kelola galeri foto kegiatan sekolah.',
        'perpustakaan_literasi' => 'Kelola materi, pertanyaan, jawaban, dan analisa Literasi Numerasi.',
        'sarpras_bosp_inventory' => 'Kelola daftar inventaris barang yang dibeli dari BOSP.',
        'sarpras_sticker_settings' => 'Atur logo, ukuran, dan teks stiker inventaris sarpras.',
        'sarpras_room_inventory' => 'Kelola inventaris barang per ruang atau gedung.',
        'sarpras_activity' => 'Kelola dokumentasi pekerjaan dan perbaikan sarpras.',
        'sarpras_monthly_agenda' => 'Kelola agenda bulanan tindak lanjut sarpras.',
        'penilaian' => 'Akses ASTS, ASAS, nilai, rekap, rapor, dan pengaturan Penilaian.',
    ];

    public static function levelOptions(): array
    {
        return [
            self::NONE => 'Sembunyikan',
            self::VIEW => 'Lihat Saja',
            self::MANAGE => 'Kelola Penuh',
        ];
    }

    /**
     * @return Collection<int, array{prefix:string,class:string,label:string,group:string,group_label:string,parent_label:?string,description:string}>
     */
    public static function definitions(): Collection
    {
        return collect(self::RESOURCE_MAP)->map(function (string $class, string $prefix): array {
            $group = User::normalizeNavigationGroupKey(AdminSchoolNavigation::effectiveGroupForClass($class));
            $parentLabel = AdminSchoolNavigation::parentItemForClass($class);

            return [
                'prefix' => $prefix,
                'class' => $class,
                'label' => $class::getNavigationLabel() ?: class_basename($class),
                'group' => $group,
                'group_label' => User::navigationGroupLabel($group),
                'parent_label' => $parentLabel,
                'description' => self::MODULE_DESCRIPTIONS[$prefix] ?? 'Atur akses modul ini.',
            ];
        })->values();
    }

    /**
     * @return array<int, string>
     */
    public static function prefixes(): array
    {
        return array_keys(self::RESOURCE_MAP);
    }

    public static function definition(string $prefix): ?array
    {
        return self::definitions()->firstWhere('prefix', $prefix);
    }

    /**
     * @return array<string, string>
     */
    public static function normalizeLevels(array $levels): array
    {
        $normalized = [];

        foreach (self::prefixes() as $prefix) {
            $value = (string) ($levels[$prefix] ?? self::NONE);
            $normalized[$prefix] = in_array($value, [self::NONE, self::VIEW, self::MANAGE], true)
                ? $value
                : self::NONE;
        }

        return $normalized;
    }

    public static function resolveEffectiveLevel(User $user, string $prefix): string
    {
        if ($user->hasFullAdminAccess()) {
            return self::MANAGE;
        }

        $storedLevels = $user->module_access_levels;

        if (is_array($storedLevels) && array_key_exists($prefix, $storedLevels)) {
            $level = (string) $storedLevels[$prefix];

            if (in_array($level, [self::NONE, self::VIEW, self::MANAGE], true)) {
                return $level;
            }
        }

        if ($user->can("{$prefix}.manage")) {
            return self::MANAGE;
        }

        if ($user->can("{$prefix}.view")) {
            return self::VIEW;
        }

        return self::NONE;
    }

    /**
     * @return array<string, string>
     */
    public static function effectiveLevels(User $user): array
    {
        if ($user->hasFullAdminAccess()) {
            return array_fill_keys(self::prefixes(), self::MANAGE);
        }

        $storedLevels = self::storedLevelsSnapshot($user);

        if ($storedLevels !== null) {
            return $storedLevels;
        }

        return collect(self::prefixes())
            ->mapWithKeys(fn (string $prefix): array => [$prefix => self::resolveEffectiveLevel($user, $prefix)])
            ->all();
    }

    /**
     * @return array<string, string>|null
     */
    protected static function storedLevelsSnapshot(User $user): ?array
    {
        $storedLevels = $user->module_access_levels;

        if (! is_array($storedLevels)) {
            return null;
        }

        $normalized = self::normalizeLevels($storedLevels);

        foreach (self::prefixes() as $prefix) {
            if (! array_key_exists($prefix, $storedLevels)) {
                return null;
            }

            $value = (string) $storedLevels[$prefix];

            if (! in_array($value, [self::NONE, self::VIEW, self::MANAGE], true)) {
                return null;
            }
        }

        return $normalized;
    }

    /**
     * @param  Collection<int, string>  $roleNames
     * @return array<string, string>
     */
    public static function defaultLevelsForRoleNames(Collection $roleNames): array
    {
        $permissions = Role::query()
            ->whereIn('name', $roleNames->all())
            ->with('permissions:id,name')
            ->get()
            ->flatMap(fn (Role $role): array => $role->permissions->pluck('name')->all())
            ->unique()
            ->values()
            ->all();

        return collect(self::prefixes())
            ->mapWithKeys(fn (string $prefix): array => [$prefix => self::levelFromPermissionNames($permissions, $prefix)])
            ->all();
    }

    public static function levelFromPermissionNames(array $permissionNames, string $prefix): string
    {
        if (in_array("{$prefix}.manage", $permissionNames, true)) {
            return self::MANAGE;
        }

        if (in_array("{$prefix}.view", $permissionNames, true)) {
            return self::VIEW;
        }

        return self::NONE;
    }

    /**
     * @return array<int, string>
     */
    public static function managedPermissionNames(): array
    {
        return collect(self::prefixes())
            ->flatMap(fn (string $prefix): array => ["{$prefix}.view", "{$prefix}.manage"])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function itemClassesForLevels(array $levels): array
    {
        $normalizedLevels = self::normalizeLevels($levels);

        $classes = collect($normalizedLevels)
            ->filter(fn (string $level): bool => $level !== self::NONE)
            ->keys()
            ->flatMap(fn (string $prefix): array => [
                self::RESOURCE_MAP[$prefix],
                ...(self::ADDITIONAL_MODULE_CLASSES[$prefix] ?? []),
            ])
            ->values();

        if (($normalizedLevels['sarpras_bosp_inventory'] ?? self::NONE) === self::MANAGE) {
            $classes->push(SarprasStickerSettings::class);
        }

        return $classes
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $advancedItems
     * @return array<int, string>
     */
    public static function deriveNavigationItems(array $levels, array $advancedItems = []): array
    {
        return collect([Dashboard::class])
            ->merge(self::itemClassesForLevels($levels))
            ->merge($advancedItems)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $itemClasses
     * @return array<int, string>
     */
    public static function deriveNavigationGroups(array $itemClasses): array
    {
        return collect($itemClasses)
            ->map(function (string $class): ?string {
                if ($class === Dashboard::class) {
                    return 'Dashboard';
                }

                if (! class_exists($class)) {
                    return null;
                }

                return User::normalizeNavigationGroupKey(
                    AdminSchoolNavigation::effectiveGroupForClass($class),
                );
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function advancedNavigationItemOptions(): array
    {
        $moduleClasses = collect(self::RESOURCE_MAP)
            ->flatMap(fn (string $class, string $prefix): array => [
                $class,
                ...(self::ADDITIONAL_MODULE_CLASSES[$prefix] ?? []),
            ])
            ->values()
            ->all();

        return collect(AdminNavigationSupport::availableNavigationItemOptions())
            ->reject(fn (string $label, string $class): bool => in_array($class, $moduleClasses, true) || $class === Dashboard::class)
            ->all();
    }
}
