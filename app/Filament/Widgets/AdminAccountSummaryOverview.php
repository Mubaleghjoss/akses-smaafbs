<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\DashboardProker;
use App\Filament\Resources\DataSiswaResource;
use App\Filament\Resources\GuruTendikResource;
use App\Filament\Resources\ProkerResource;
use App\Filament\Resources\SarprasBospInventoryResource;
use App\Models\DataSiswa;
use App\Models\GuruTendik;
use App\Models\Proker;
use App\Models\SarprasBospInventory;
use App\Models\User;
use App\Support\Admin\Dashboard\DashboardCacheSupport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class AdminAccountSummaryOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user() instanceof User;
    }

    protected function getStats(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return collect($this->summaryItems($user))
            ->map(function (array $item): Stat {
                $stat = Stat::make($item['label'], $item['value'])
                    ->description($item['description'])
                    ->color($item['color']);

                return filled($item['url'] ?? null)
                    ? $stat->url($item['url'])
                    : $stat;
            })
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: string, description: string, color: string, url?: string|null}>
     */
    protected function summaryItems(User $user): array
    {
        $user->loadMissing('roles');

        $cacheKey = 'user-'.$user->id.'-'.md5(json_encode([
            'roles' => $user->getRoleNames()->values()->all(),
            'groups' => $user->allowed_navigation_groups,
            'items' => $user->allowed_navigation_items,
            'levels' => $user->module_access_levels,
            'guru_tendik_id' => $user->guru_tendik_id,
            'boarding_angkatan_scope' => $user->boarding_angkatan_scope,
            'boarding_rombel_scope' => $user->boarding_rombel_scope,
            'guru_walas_scope' => $user->guru_walas_scope,
        ]));

        return DashboardCacheSupport::remember(
            'admin_account_summary',
            $cacheKey,
            fn (): array => $this->buildSummaryItems($user),
            60,
        );
    }

    /**
     * @return array<int, array{label: string, value: string, description: string, color: string, url?: string|null}>
     */
    protected function buildSummaryItems(User $user): array
    {
        $items = [];
        $groupsCount = count($user->resolvedNavigationGroups());
        $navigationItemsCount = $user->hasFullAdminAccess() ? null : count($user->resolvedNavigationItems());

        $items[] = [
            'label' => 'Grup Menu',
            'value' => $this->formatNumber($groupsCount),
            'description' => $user->hasFullAdminAccess()
                ? 'Akun admin dapat membuka semua grup navigasi.'
                : $this->formatNumber($navigationItemsCount ?? 0).' menu aktif sesuai akses akun.',
            'color' => 'primary',
            'url' => null,
        ];

        if ($user->uses_default_password) {
            $items[] = [
                'label' => 'Password Default',
                'value' => '1',
                'description' => 'Akun ini masih memakai password awal.',
                'color' => 'danger',
                'url' => null,
            ];
        }

        if (SchemaFacade::hasTable('webauthn_credentials')) {
            $items[] = [
                'label' => 'Passkey',
                'value' => $this->formatNumber($user->webauthnCredentials()->count()),
                'description' => 'Credential passkey yang terhubung ke akun ini.',
                'color' => 'info',
                'url' => null,
            ];
        }

        if ($this->canViewModule($user, 'data_siswa') && SchemaFacade::hasTable('data_siswa')) {
            $items[] = [
                'label' => 'Siswa Aktif',
                'value' => $this->formatNumber(
                    DataSiswa::query()
                        ->visibleToUser($user)
                        ->where('status', 'aktif')
                        ->count()
                ),
                'description' => 'Jumlah siswa aktif yang bisa dibaca akun ini.',
                'color' => 'success',
                'url' => DataSiswaResource::canAccess()
                    ? DataSiswaResource::getUrl('index', $this->filterParameters('status', 'aktif'))
                    : null,
            ];
        }

        if ($this->canViewModule($user, 'guru_tendik') && SchemaFacade::hasTable('guru_tendik')) {
            $items[] = [
                'label' => 'Guru/Tendik',
                'value' => $this->formatNumber(
                    GuruTendik::query()
                        ->visibleToUser($user)
                        ->count()
                ),
                'description' => 'Data guru/tendik yang terlihat oleh akun ini.',
                'color' => 'warning',
                'url' => GuruTendikResource::canAccess() ? GuruTendikResource::getUrl('index') : null,
            ];
        }

        if ($this->canViewProker($user) && SchemaFacade::hasTable('prokers')) {
            $items[] = [
                'label' => 'Proker 30 Hari',
                'value' => $this->formatNumber($this->upcomingProkerCount()),
                'description' => 'Target selesai dari hari ini sampai 1 bulan ke depan.',
                'color' => 'danger',
                'url' => DashboardProker::canAccess() ? DashboardProker::getUrl() : (ProkerResource::canAccess() ? ProkerResource::getUrl('index') : null),
            ];
        }

        if ($this->canViewModule($user, 'sarpras_bosp_inventory') && SchemaFacade::hasTable('sarpras_bosp_inventories')) {
            $items[] = [
                'label' => 'Inventaris BOSP',
                'value' => $this->formatNumber(SarprasBospInventory::query()->count()),
                'description' => 'Barang BOSP yang tercatat di inventaris sarpras.',
                'color' => 'gray',
                'url' => SarprasBospInventoryResource::canAccess() ? SarprasBospInventoryResource::getUrl('index') : null,
            ];
        }

        return $items;
    }

    protected function canViewModule(User $user, string $prefix): bool
    {
        return $user->hasFullAdminAccess() || $user->canViewModule($prefix);
    }

    protected function canViewProker(User $user): bool
    {
        return $this->canViewModule($user, 'proker_dashboard') || $this->canViewModule($user, 'proker');
    }

    protected function upcomingProkerCount(): int
    {
        return Proker::query()
            ->whereNotNull('target_selesai')
            ->whereDate('target_selesai', '>=', now()->toDateString())
            ->whereDate('target_selesai', '<=', now()->copy()->addMonth()->toDateString())
            ->where('status', '!=', 'selesai')
            ->count();
    }

    /**
     * @return array<string, string>
     */
    protected function filterParameters(string $name, string $value): array
    {
        return [
            "filters[{$name}][value]" => $value,
            "tableFilters[{$name}][value]" => $value,
        ];
    }

    protected function formatNumber(?int $value): string
    {
        return number_format((int) $value, 0, ',', '.');
    }
}
