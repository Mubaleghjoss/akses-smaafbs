<?php

namespace App\Support\Admin;

use App\Filament\Pages\Assessment\AsasHub;
use App\Filament\Pages\Assessment\AssessmentDashboard;
use App\Filament\Pages\Assessment\AstsHub;
use App\Filament\Pages\DashboardProker;
use App\Filament\Pages\Monitor;
use App\Filament\Pages\SarprasStickerSettings;
use App\Filament\Resources\BeritaResource;
use App\Filament\Resources\BerkasGuruResource;
use App\Filament\Resources\BerkasSiswaResource;
use App\Filament\Resources\CalendarEventResource;
use App\Filament\Resources\CatatanBkResource;
use App\Filament\Resources\DataSiswaResource;
use App\Filament\Resources\DokumenKomiteResource;
use App\Filament\Resources\EventTimelineResource;
use App\Filament\Resources\GaleriResource;
use App\Filament\Resources\GuruTendikResource;
use App\Filament\Resources\HotspotUserResource;
use App\Filament\Resources\BlockedDomainResource;
use App\Filament\Resources\JenisBerkasResource;
use App\Filament\Resources\PerpustakaanBukuResource;
use App\Filament\Resources\PerpustakaanKategoriResource;
use App\Filament\Resources\PerpustakaanLemariResource;
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
use App\Filament\Resources\VisiMisiResource;
use Filament\Navigation\NavigationItem;

class AdminSchoolNavigation
{
    public const GROUP = 'Manajemen Sekolah';

    /**
     * @var array<string, array{icon:string,sort:int,group?:string}>
     */
    protected const PARENT_DEFINITIONS = [
        'Sarpras' => ['icon' => 'heroicon-o-wrench-screwdriver', 'sort' => 100],
        'BK' => ['icon' => 'heroicon-o-chat-bubble-left-right', 'sort' => 110],
        'Kurikulum' => ['icon' => 'heroicon-o-academic-cap', 'sort' => 120],
        'Kesiswaan' => ['icon' => 'heroicon-o-user-group', 'sort' => 130],
        'Proker' => ['icon' => 'heroicon-o-clipboard-document-list', 'sort' => 140],
        'Humas' => ['icon' => 'heroicon-o-megaphone', 'sort' => 150],
        'UKS' => ['icon' => 'heroicon-o-heart', 'sort' => 160],
        'Siswa' => ['icon' => 'heroicon-o-users', 'sort' => 170],
        'Guru' => ['icon' => 'heroicon-o-user-circle', 'sort' => 180],
        'Agenda' => ['icon' => 'heroicon-o-calendar-days', 'sort' => 190],
        'Konten' => ['icon' => 'heroicon-o-newspaper', 'sort' => 200],
        'Perpustakaan' => ['icon' => 'heroicon-o-book-open', 'sort' => 210],
        'Penilaian' => ['icon' => 'heroicon-o-academic-cap', 'sort' => 115],
        'IT SMA AFBS' => ['icon' => 'heroicon-o-server', 'sort' => 220],
    ];

    /**
     * @var array<class-string, string>
     */
    protected const CLASS_PARENT_MAP = [
        SarprasBospInventoryResource::class => 'Sarpras',
        SarprasRoomInventoryResource::class => 'Sarpras',
        SarprasActivityResource::class => 'Sarpras',
        SarprasMonthlyAgendaResource::class => 'Sarpras',
        SarprasStickerSettings::class => 'Sarpras',
        CatatanBkResource::class => 'BK',
        VisiMisiResource::class => 'Kurikulum',
        SurveiResource::class => 'Kesiswaan',
        DashboardProker::class => 'Proker',
        ProkerResource::class => 'Proker',
        ProkerBidangResource::class => 'Proker',
        ProfilSekolahResource::class => 'Humas',
        StrukturOrganisasiResource::class => 'Humas',
        StrukturKomiteResource::class => 'Humas',
        DokumenKomiteResource::class => 'Humas',
        UksRecordResource::class => 'UKS',
        DataSiswaResource::class => 'Siswa',
        RombelResource::class => 'Siswa',
        BerkasSiswaResource::class => 'Siswa',
        PrestasiResource::class => 'Siswa',
        GuruTendikResource::class => 'Guru',
        BerkasGuruResource::class => 'Guru',
        JenisBerkasResource::class => 'Guru',
        Monitor::class => 'IT SMA AFBS',
        HotspotUserResource::class => 'IT SMA AFBS',
        BlockedDomainResource::class => 'IT SMA AFBS',
        CalendarEventResource::class => 'Agenda',
        EventTimelineResource::class => 'Agenda',
        BeritaResource::class => 'Konten',
        GaleriResource::class => 'Konten',
        PerpustakaanBukuResource::class => 'Perpustakaan',
        PerpustakaanKategoriResource::class => 'Perpustakaan',
        PerpustakaanLemariResource::class => 'Perpustakaan',
        PerpustakaanLiterasiMaterialResource::class => 'Perpustakaan',
        AssessmentDashboard::class => 'Penilaian',
        AstsHub::class => 'Penilaian',
        AsasHub::class => 'Penilaian',
    ];

    /**
     * @var array<class-string, string>
     */
    protected const CLASS_GROUP_MAP = [
        AssessmentDashboard::class => self::GROUP,
        AstsHub::class => self::GROUP,
        AsasHub::class => self::GROUP,
    ];

    public static function shouldClassify(string $class): bool
    {
        return array_key_exists($class, self::CLASS_PARENT_MAP)
            || array_key_exists($class, self::CLASS_GROUP_MAP);
    }

    public static function shouldRegisterAssessmentClass(string $class): bool
    {
        return in_array($class, [
            AssessmentDashboard::class,
            AstsHub::class,
            AsasHub::class,
        ], true);
    }

    public static function effectiveGroupForClass(string $class): string|\UnitEnum|null
    {
        if (array_key_exists($class, self::CLASS_GROUP_MAP)) {
            return self::CLASS_GROUP_MAP[$class];
        }

        if (array_key_exists($class, self::CLASS_PARENT_MAP)) {
            return self::GROUP;
        }

        return $class::getNavigationGroup();
    }

    public static function parentItemForClass(string $class): ?string
    {
        return self::CLASS_PARENT_MAP[$class] ?? null;
    }

    /**
     * @param  array<int, NavigationItem>  $items
     * @return array<int, NavigationItem>
     */
    public static function decorateNavigationItems(string $class, array $items): array
    {
        if (! self::shouldClassify($class)) {
            return $items;
        }

        $group = self::effectiveGroupForClass($class);
        $parent = self::parentItemForClass($class);

        return array_map(function (NavigationItem $item) use ($group, $parent): NavigationItem {
            $item->group($group);

            if (filled($parent)) {
                $item->parentItem($parent);
            }

            return $item;
        }, $items);
    }

    /**
     * @param  iterable<class-string>  $classes
     * @return array<int, NavigationItem>
     */
    public static function parentNavigationItems(iterable $classes): array
    {
        return collect($classes)
            ->map(fn (string $class): ?string => self::parentItemForClass($class))
            ->filter()
            ->unique()
            ->map(function (string $label): NavigationItem {
                $definition = self::PARENT_DEFINITIONS[$label];

                return NavigationItem::make($label)
                    ->group($definition['group'] ?? self::GROUP)
                    ->icon($definition['icon'])
                    ->sort($definition['sort']);
            })
            ->values()
            ->all();
    }

    public static function optionLabelForClass(string $class): string
    {
        $group = self::effectiveGroupForClass($class);
        $groupLabel = (string) $group;
        $parent = self::parentItemForClass($class);
        $label = method_exists($class, 'getNavigationLabel')
            ? ($class::getNavigationLabel() ?: class_basename($class))
            : class_basename($class);

        return collect([$groupLabel, $parent, $label])
            ->filter()
            ->implode(' -> ');
    }
}
