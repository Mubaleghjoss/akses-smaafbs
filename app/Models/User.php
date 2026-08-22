<?php

namespace App\Models;

use App\Support\Admin\AdminModuleAccess;
use App\Support\Admin\Dashboard\DashboardCacheSupport;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const BOARDING_PAMONG_ROLES = [
        'pamong_putra',
        'pamong_putri',
    ];

    public const FULL_ADMIN_ROLES = [
        'admin',
        'guru_admin',
        'super_admin',
    ];

    public const NAVIGATION_GROUP_OPTIONS = [
        'Dashboard' => 'Dashboard',
        'Manajemen Sekolah' => 'Manajemen Sekolah',
        'Boarding' => 'Boarding',
        'Perizinan' => 'Perizinan',
        'Pengaturan' => 'Pengaturan',
        'Konten' => 'Konten',
        'Agenda' => 'Agenda',
        'Siswa' => 'Siswa',
        'Guru/Tendik' => 'Guru/Tendik',
        'UKS' => 'UKS',
    ];

    protected static ?bool $permissionRelationsAvailable = null;

    protected bool $updatingToDefaultPassword = false;

    protected ?array $resolvedNavigationGroupsCache = null;

    protected ?array $resolvedNavigationItemsCache = null;

    protected $fillable = [
        'name',
        'username',
        'email',
        'avatar_path',
        'password',
        'boarding_angkatan_scope',
        'boarding_rombel_scope',
        'guru_tendik_id',
        'guru_mapel_label',
        'guru_walas_scope',
        'allowed_navigation_groups',
        'allowed_navigation_items',
        'module_access_levels',
        'uses_default_password',
        'default_password_reset_at',
        'default_password_changed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'guru_tendik_id' => 'integer',
            'boarding_rombel_scope' => 'array',
            'guru_walas_scope' => 'array',
            'allowed_navigation_groups' => 'array',
            'allowed_navigation_items' => 'array',
            'module_access_levels' => 'array',
            'uses_default_password' => 'boolean',
            'default_password_reset_at' => 'datetime',
            'default_password_changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if (! $user->isDirty('password')) {
                return;
            }

            if (! self::defaultPasswordTrackingColumnsAvailable()) {
                return;
            }

            if (! $user->updatingToDefaultPassword && ! $user->isDirty('uses_default_password')) {
                $user->uses_default_password = false;
            }

            if ($user->uses_default_password) {
                $user->default_password_changed_at = null;

                return;
            }

            $user->default_password_changed_at = Carbon::now();
        });

        $invalidateDashboardCaches = static function (): void {
            DashboardCacheSupport::forgetModule('user_credentials');
        };

        static::saved($invalidateDashboardCaches);
        static::deleted($invalidateDashboardCaches);
    }

    public function updateToDefaultPassword(string $plainPassword): void
    {
        if (! self::defaultPasswordTrackingColumnsAvailable()) {
            $this->forceFill([
                'password' => $plainPassword,
            ])->save();

            return;
        }

        $this->updatingToDefaultPassword = true;

        $this->forceFill([
            'password' => $plainPassword,
            'uses_default_password' => true,
            'default_password_reset_at' => Carbon::now(),
            'default_password_changed_at' => null,
        ])->save();

        $this->updatingToDefaultPassword = false;
    }

    protected static function defaultPasswordTrackingColumnsAvailable(): bool
    {
        return SchemaFacade::hasTable('users')
            && SchemaFacade::hasColumn('users', 'uses_default_password')
            && SchemaFacade::hasColumn('users', 'default_password_reset_at')
            && SchemaFacade::hasColumn('users', 'default_password_changed_at');
    }

    public static function navigationGroupOptions(): array
    {
        return self::NAVIGATION_GROUP_OPTIONS;
    }

    public static function normalizeNavigationGroupKey(?string $group): string
    {
        return filled($group) ? $group : 'Dashboard';
    }

    public static function navigationGroupLabel(string $group): string
    {
        return self::navigationGroupOptions()[$group] ?? $group;
    }

    public static function boardingPamongRoleNames(): array
    {
        return self::BOARDING_PAMONG_ROLES;
    }

    public static function fullAdminRoleNames(): array
    {
        return self::FULL_ADMIN_ROLES;
    }

    public static function permissionRelationsAreAvailable(): bool
    {
        if (static::$permissionRelationsAvailable !== null) {
            return static::$permissionRelationsAvailable;
        }

        $tableNames = config('permission.table_names', []);

        return static::$permissionRelationsAvailable = collect([
            $tableNames['roles'] ?? 'roles',
            $tableNames['permissions'] ?? 'permissions',
            $tableNames['model_has_roles'] ?? 'model_has_roles',
            $tableNames['model_has_permissions'] ?? 'model_has_permissions',
            $tableNames['role_has_permissions'] ?? 'role_has_permissions',
        ])->every(fn (string $table): bool => SchemaFacade::hasTable($table));
    }

    public static function boardingPamongQuery(): Builder
    {
        return self::query()->whereHas('roles', function (Builder $query): void {
            $query->whereIn('name', self::boardingPamongRoleNames());
        });
    }

    public static function searchBoardingPamongOptions(string $search = '', int $limit = 50): array
    {
        return static::applyUserSearch(static::boardingPamongQuery(), $search, $limit);
    }

    public static function searchNameOptions(string $search = '', int $limit = 50): array
    {
        return static::applyUserSearch(static::query(), $search, $limit);
    }

    public static function searchGuruAccountOptions(?int $excludeUserId = null, string $search = '', int $limit = 50): array
    {
        $query = static::query()
            ->whereHas('roles', fn (Builder $builder): Builder => $builder->where('name', 'guru'));

        if ($excludeUserId) {
            $query->whereKeyNot($excludeUserId);
        }

        return static::applyUserSearch($query, $search, $limit);
    }

    public static function resolveNameOptionLabel(mixed $value): ?string
    {
        $id = (int) $value;

        if ($id <= 0) {
            return null;
        }

        return static::query()->whereKey($id)->value('name');
    }

    public function guruTendik(): BelongsTo
    {
        return $this->belongsTo(GuruTendik::class, 'guru_tendik_id');
    }

    public function webauthnCredentials(): HasMany
    {
        return $this->hasMany(WebAuthnCredential::class);
    }

    public function webauthnChallenges(): HasMany
    {
        return $this->hasMany(WebAuthnChallenge::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole([
            ...self::fullAdminRoleNames(),
            'tu',
            'bendahara',
            'pamong_putra',
            'pamong_putri',
            'kepala_perpus',
            'guru_uks',
            'guru',
            'kurikulum',
            'guru_mapel',
            'wali_kelas',
            'kepala_sekolah',
        ]);
    }

    public function hasFullAdminAccess(): bool
    {
        return $this->hasAnyRole(self::fullAdminRoleNames());
    }

    public function isBoardingPamong(): bool
    {
        return $this->hasAnyRole(self::boardingPamongRoleNames());
    }

    public function isGuru(): bool
    {
        return $this->hasAnyRole(['guru', 'guru_admin', 'guru_mapel', 'wali_kelas']);
    }

    public function usesGuruPersonalScope(): bool
    {
        return $this->isGuru() && ! $this->hasFullAdminAccess();
    }

    public function shouldForceDefaultPasswordChange(): bool
    {
        return $this->isGuru() && (bool) $this->uses_default_password;
    }

    public function boardingGenderScope(): ?string
    {
        return match (true) {
            $this->hasRole('pamong_putra') => 'L',
            $this->hasRole('pamong_putri') => 'P',
            default => null,
        };
    }

    public function boardingAngkatanScope(): ?string
    {
        $scope = trim((string) $this->boarding_angkatan_scope);

        return $scope !== '' ? $scope : null;
    }

    public function boardingRombelScopes(): array
    {
        return collect($this->boarding_rombel_scope ?? [])
            ->map(fn ($scope): string => trim((string) $scope))
            ->filter()
            ->values()
            ->all();
    }

    public function guruWalasScopes(): array
    {
        return collect($this->guru_walas_scope ?? [])
            ->map(fn ($scope): string => trim((string) $scope))
            ->filter()
            ->values()
            ->all();
    }

    public function resolvedNavigationGroups(): array
    {
        if ($this->resolvedNavigationGroupsCache !== null) {
            return $this->resolvedNavigationGroupsCache;
        }

        if ($this->hasFullAdminAccess()) {
            return $this->resolvedNavigationGroupsCache = array_keys(self::navigationGroupOptions());
        }

        return $this->resolvedNavigationGroupsCache = AdminModuleAccess::deriveNavigationGroups($this->resolvedNavigationItems());
    }

    public function resolvedNavigationItems(): array
    {
        if ($this->resolvedNavigationItemsCache !== null) {
            return $this->resolvedNavigationItemsCache;
        }

        if ($this->hasFullAdminAccess()) {
            return $this->resolvedNavigationItemsCache = [];
        }

        $advancedItems = collect($this->allowed_navigation_items ?? [])
            ->map(fn ($class): string => trim((string) $class))
            ->filter()
            ->values()
            ->all();

        return $this->resolvedNavigationItemsCache = AdminModuleAccess::deriveNavigationItems(
            AdminModuleAccess::effectiveLevels($this),
            $advancedItems,
        );
    }

    public function explicitModuleAccessLevels(): array
    {
        return AdminModuleAccess::normalizeLevels($this->module_access_levels ?? []);
    }

    public function moduleAccessLevel(string $prefix): string
    {
        return AdminModuleAccess::resolveEffectiveLevel($this, $prefix);
    }

    public function canViewModule(string $prefix): bool
    {
        return in_array($this->moduleAccessLevel($prefix), [AdminModuleAccess::VIEW, AdminModuleAccess::MANAGE], true);
    }

    public function canManageModule(string $prefix): bool
    {
        return $this->moduleAccessLevel($prefix) === AdminModuleAccess::MANAGE;
    }

    public function applyBoardingStudentScope(Builder $query): Builder
    {
        if (! $this->isBoardingPamong()) {
            return $query;
        }

        if ($gender = $this->boardingGenderScope()) {
            $query->where('jk', $gender);
        }

        $rombelScopes = $this->boardingRombelScopes();

        if ($rombelScopes !== []) {
            $query->whereIn('rombel_saat_ini', $rombelScopes);
        }

        if ($angkatan = $this->boardingAngkatanScope()) {
            $query->where('rombel_saat_ini', 'like', '%'.$angkatan.'%');
        }

        return $query;
    }

    public function resolvedAvatarPath(): ?string
    {
        $avatarPath = trim((string) $this->avatar_path);

        if ($avatarPath !== '') {
            return $avatarPath;
        }

        $this->loadMissing('guruTendik:id,foto_profil');
        $guruPhoto = trim((string) $this->guruTendik?->foto_profil);

        return $guruPhoto !== '' ? $guruPhoto : null;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        $path = $this->resolvedAvatarPath();

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function avatarSourceLabel(): string
    {
        if (filled(trim((string) $this->avatar_path))) {
            return 'Avatar akun custom';
        }

        $this->loadMissing('guruTendik:id,foto_profil');

        if (filled(trim((string) $this->guruTendik?->foto_profil))) {
            return 'Foto profil guru/tendik';
        }

        return 'Inisial nama';
    }

    protected static function applyUserSearch(Builder $query, string $search, int $limit): array
    {
        $search = trim($search);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('username', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        return $query
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name', 'id')
            ->all();
    }
}

