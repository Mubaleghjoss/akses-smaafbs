<?php

namespace App\Filament\Pages\Bk;

use App\Filament\Resources\BkKasusResource;
use App\Models\BkKasus;
use App\Models\User;
use App\Support\Bk\BkSigapRecap;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Livewire\Attributes\Url;

class RekapSigapPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Rekap SIGAP';

    protected static ?string $title = 'Rekap Laporan SIGAP';

    protected static ?int $navigationSort = 35;

    protected static ?string $permissionPrefix = 'bk_kasus';

    protected string $view = 'filament.pages.bk.rekap-sigap';

    #[Url(as: 'dari')]
    public ?string $dari = null;

    #[Url(as: 'sampai')]
    public ?string $sampai = null;

    #[Url(as: 'kategori')]
    public ?string $kategori = null;

    #[Url(as: 'tingkat')]
    public ?string $tingkat = null;

    protected ?array $recapCache = null;

    public static function shouldRegisterNavigation(): bool
    {
        return static::hasRequiredTables() && static::userCanModule('view');
    }

    public static function canAccess(): bool
    {
        return static::hasRequiredTables() && static::userCanModule('view');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        if (blank($this->dari)) {
            $this->dari = now()->startOfMonth()->toDateString();
        }

        if (blank($this->sampai)) {
            $this->sampai = now()->toDateString();
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['dari', 'sampai', 'kategori', 'tingkat'], true)) {
            $this->recapCache = null;
        }
    }

    public function terapkanBulanIni(): void
    {
        $this->dari = now()->startOfMonth()->toDateString();
        $this->sampai = now()->toDateString();
        $this->recapCache = null;
    }

    public function terapkanBulanLalu(): void
    {
        $this->dari = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $this->sampai = now()->subMonthNoOverflow()->endOfMonth()->toDateString();
        $this->recapCache = null;
    }

    public function terapkanSemester(): void
    {
        $now = now();
        $start = $now->month >= 7
            ? $now->copy()->setDate($now->year, 7, 1)
            : $now->copy()->setDate($now->year, 1, 1);

        $this->dari = $start->toDateString();
        $this->sampai = $now->toDateString();
        $this->recapCache = null;
    }

    public function resetFilter(): void
    {
        $this->kategori = null;
        $this->tingkat = null;
        $this->recapCache = null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRecapProperty(): array
    {
        return $this->recapCache ??= BkSigapRecap::build(
            $this->dari,
            $this->sampai,
            $this->kategori,
            $this->tingkat,
        );
    }

    /**
     * @return array<string, string>
     */
    public function getKategoriOptionsProperty(): array
    {
        return BkKasus::kategoriOptions();
    }

    /**
     * @return array<string, string>
     */
    public function getTingkatOptionsProperty(): array
    {
        return BkKasus::tingkatOptions();
    }

    public function getPeriodeLabelProperty(): string
    {
        $recap = $this->recap;

        return Carbon::parse($recap['periode']['dari'])->translatedFormat('d F Y')
            .' s.d. '
            .Carbon::parse($recap['periode']['sampai'])->translatedFormat('d F Y');
    }

    protected function getViewData(): array
    {
        return [
            'recap' => $this->recap,
            'kategoriOptions' => $this->kategoriOptions,
            'tingkatOptions' => $this->tingkatOptions,
            'periodeLabel' => $this->periodeLabel,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kelolaKasus')
                ->label('Kelola Laporan SIGAP')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->url(fn (): string => BkKasusResource::getUrl())
                ->visible(fn (): bool => BkKasusResource::canViewAny()),
        ];
    }

    protected static function hasRequiredTables(): bool
    {
        return SchemaFacade::hasTable('bk_kasus')
            && SchemaFacade::hasTable('bk_kasus_siswa')
            && SchemaFacade::hasTable('data_siswa');
    }

    protected static function userCanModule(string $ability): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $user->loadMissing('roles');

        if ($user->hasFullAdminAccess()) {
            return true;
        }

        $prefix = static::$permissionPrefix;

        if (blank($prefix)) {
            return false;
        }

        return $ability === 'view'
            ? $user->canViewModule($prefix)
            : $user->canManageModule($prefix);
    }
}
