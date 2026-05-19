<?php

namespace App\Support\Admin;

use App\Models\GuruTendik;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class AdminRoleTemplateSupport
{
    /**
     * @var array<string, array<int, string>>
     */
    protected static array $matchedTemplateKeysForUserCache = [];

    /**
     * @var array<string, array<int, string>>
     */
    protected static array $matchedTemplateLabelsForUserCache = [];

    /**
     * @var array<int, array<string, array<int, string>>>
     */
    protected static array $suggestedTemplateReasonsForGuruCache = [];

    /**
     * @var array<int, string>
     */
    protected static array $suggestedTemplateReasonSummaryForGuruCache = [];

    /**
     * @var array<string, array<int, string>>
     */
    protected const TASK_KEYWORDS = [
        'sarpras' => ['sarpras', 'inventaris', 'perlengkapan', 'prasarana', 'fasilitas', 'laboratorium', 'lab ', 'labotarium', 'aset', 'barang'],
        'bk' => ['bk', 'bimbingan', 'konseling'],
        'kurikulum' => ['kurikulum', 'waka kurikulum'],
        'kesiswaan' => ['kesiswaan', 'waka kesiswaan'],
        'proker' => ['proker', 'program kerja', 'rencana kerja'],
        'humas' => ['humas', 'publikasi', 'media', 'komite', 'hubungan masyarakat', 'website', 'konten', 'dokumentasi', 'sosial media', 'sosmed'],
        'uks' => ['uks', 'kesehatan', 'pmr'],
        'operator_siswa' => ['operator siswa', 'data siswa', 'dapodik siswa', 'admin siswa'],
        'operator_guru' => ['operator guru', 'kepegawaian', 'data guru', 'tendik', 'dapodik guru', 'ptk'],
        'perpustakaan' => ['perpustakaan', 'literasi', 'library', 'pustaka', 'buku'],
        'pamong_putra' => ['pamong putra', 'musyrif putra'],
        'pamong_putri' => ['pamong putri', 'musyrifah putri'],
    ];

    /**
     * @return array<string, array{label:string,description:string,roles:array<int, string>,manage:array<int, string>,view:array<int, string>,allowed_navigation_items?:array<int, string>}>
     */
    public static function definitions(): array
    {
        return [
            'sarpras' => [
                'label' => 'Sarpras',
                'description' => 'Operator sarpras dengan akses penuh ke inventaris, kegiatan, dan agenda bulanan sarpras.',
                'roles' => ['tu'],
                'manage' => [
                    'sarpras_bosp_inventory',
                    'sarpras_room_inventory',
                    'sarpras_activity',
                    'sarpras_monthly_agenda',
                ],
                'view' => [],
            ],
            'bk' => [
                'label' => 'BK',
                'description' => 'Operator bimbingan konseling dengan akses penuh ke catatan BK dan akses baca data siswa pendukung.',
                'roles' => ['tu'],
                'manage' => ['catatan_bk'],
                'view' => ['data_siswa', 'rombel', 'prestasi', 'berkas_siswa'],
            ],
            'kurikulum' => [
                'label' => 'Kurikulum',
                'description' => 'Operator kurikulum untuk visi misi dan referensi data utama sekolah.',
                'roles' => ['tu'],
                'manage' => ['visi_misi'],
                'view' => ['data_siswa', 'guru_tendik'],
            ],
            'kesiswaan' => [
                'label' => 'Kesiswaan',
                'description' => 'Operator kesiswaan untuk survei dan monitoring data siswa.',
                'roles' => ['tu'],
                'manage' => ['survei'],
                'view' => ['data_siswa', 'rombel', 'prestasi', 'berkas_siswa'],
            ],
            'proker' => [
                'label' => 'Proker',
                'description' => 'Operator program kerja dengan akses penuh ke dashboard, bidang, dan proker.',
                'roles' => ['tu'],
                'manage' => ['proker_dashboard', 'proker_bidang', 'proker'],
                'view' => [],
            ],
            'humas' => [
                'label' => 'Humas',
                'description' => 'Operator humas untuk identitas sekolah, struktur, dokumen komite, agenda, dan konten publik.',
                'roles' => ['tu'],
                'manage' => [
                    'profil_sekolah',
                    'struktur_organisasi',
                    'struktur_komite',
                    'dokumen_komite',
                    'calendar_events',
                    'event_timelines',
                    'berita',
                    'galeri',
                ],
                'view' => [],
            ],
            'uks' => [
                'label' => 'UKS',
                'description' => 'Petugas UKS dengan akses penuh ke rekam layanan dan akses baca data siswa.',
                'roles' => ['guru_uks'],
                'manage' => ['uks_records'],
                'view' => ['data_siswa', 'rombel'],
            ],
            'operator_siswa' => [
                'label' => 'Operator Siswa',
                'description' => 'Operator data siswa, berkas siswa, dan prestasi.',
                'roles' => ['tu'],
                'manage' => ['data_siswa', 'rombel', 'berkas_siswa', 'prestasi'],
                'view' => [],
            ],
            'operator_guru' => [
                'label' => 'Operator Guru',
                'description' => 'Operator data guru/tendik dan seluruh berkas guru.',
                'roles' => ['tu'],
                'manage' => ['guru_tendik', 'jenis_berkas', 'berkas_guru'],
                'view' => [],
            ],
            'perpustakaan' => [
                'label' => 'Perpustakaan',
                'description' => 'Operator perpustakaan untuk materi literasi, pertanyaan, responden, dan analisa plagiat.',
                'roles' => ['kepala_perpus'],
                'manage' => ['perpustakaan_literasi'],
                'view' => ['data_siswa', 'rombel'],
            ],
            'bendahara_boarding' => [
                'label' => 'Bendahara Boarding',
                'description' => 'Bendahara untuk keuangan boarding dengan akses baca rapot dan data siswa.',
                'roles' => ['bendahara'],
                'manage' => ['boarding_keuangan'],
                'view' => ['boarding_rapot', 'data_siswa', 'rombel'],
            ],
            'pamong_putra' => [
                'label' => 'Pamong Putra',
                'description' => 'Pamong putra dengan akses penuh ke modul boarding sesuai scope siswa yang dipegang.',
                'roles' => ['pamong_putra'],
                'manage' => [
                    'boarding_rapot',
                    'boarding_pencapaian',
                    'boarding_konseling',
                    'boarding_keuangan',
                    'boarding_perizinan',
                    'catatan_bk',
                ],
                'view' => ['boarding_arsip'],
            ],
            'pamong_putri' => [
                'label' => 'Pamong Putri',
                'description' => 'Pamong putri dengan akses penuh ke modul boarding sesuai scope siswa yang dipegang.',
                'roles' => ['pamong_putri'],
                'manage' => [
                    'boarding_rapot',
                    'boarding_pencapaian',
                    'boarding_konseling',
                    'boarding_keuangan',
                    'boarding_perizinan',
                    'catatan_bk',
                ],
                'view' => ['boarding_arsip'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::definitions())
            ->mapWithKeys(fn (array $definition, string $key): array => [$key => $definition['label']])
            ->all();
    }

    /**
     * @param  array<int, string>  $templateKeys
     * @return array<int, string>
     */
    public static function normalizeTemplateKeys(array $templateKeys): array
    {
        return collect($templateKeys)
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => array_key_exists($value, self::definitions()))
            ->values()
            ->all();
    }

    public static function description(?string $key): ?string
    {
        if (! $key || ! array_key_exists($key, self::definitions())) {
            return null;
        }

        $definition = self::definitions()[$key];

        return $definition['description'];
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, string>
     */
    public static function mergedLevelsForTemplates(array $keys): array
    {
        $levels = AdminModuleAccess::normalizeLevels([]);

        foreach ($keys as $key) {
            $templateLevels = self::defaultLevelsForTemplate($key);

            foreach ($templateLevels as $prefix => $level) {
                $levels[$prefix] = self::maxLevel($levels[$prefix] ?? AdminModuleAccess::NONE, $level);
            }
        }

        return $levels;
    }

    /**
     * @param  array<int, string>  $labels
     * @return array<string, array<int, string>>
     */
    public static function suggestedTemplateReasonsForTaskLabels(array $labels): array
    {
        $normalizedLabels = collect($labels)
            ->map(fn ($label): string => trim((string) $label))
            ->filter()
            ->values();

        if ($normalizedLabels->isEmpty()) {
            return [];
        }

        $reasonMap = [];

        foreach ($normalizedLabels as $label) {
            $normalizedLabel = str($label)->lower()->squish()->value();

            foreach (self::TASK_KEYWORDS as $templateKey => $keywords) {
                foreach ($keywords as $keyword) {
                    if (! str_contains($normalizedLabel, $keyword)) {
                        continue;
                    }

                    $reasonMap[$templateKey] ??= [];
                    $reasonMap[$templateKey][] = $label;

                    break;
                }
            }
        }

        return collect($reasonMap)
            ->map(fn (array $matchedLabels): array => collect($matchedLabels)->unique()->values()->all())
            ->all();
    }

    /**
     * @param  array<int, string>  $labels
     * @return array<int, string>
     */
    public static function suggestedTemplatesForTaskLabels(array $labels): array
    {
        return collect(self::suggestedTemplateReasonsForTaskLabels($labels))
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function suggestedTemplateReasonsForGuruTendik(?GuruTendik $guruTendik): array
    {
        if (! $guruTendik?->exists) {
            return [];
        }

        $cacheKey = (int) $guruTendik->getKey();

        if (array_key_exists($cacheKey, static::$suggestedTemplateReasonsForGuruCache)) {
            return static::$suggestedTemplateReasonsForGuruCache[$cacheKey];
        }

        if ($guruTendik->relationLoaded('tugasTambahan')) {
            $labels = $guruTendik->tugasTambahan
                ->sortByDesc('tmt')
                ->pluck('tugas_tambahan')
                ->filter()
                ->values()
                ->all();
        } else {
            $labels = $guruTendik->tugasTambahan()
                ->orderByDesc('tmt')
                ->pluck('tugas_tambahan')
                ->filter()
                ->values()
                ->all();
        }

        $reasonMap = self::suggestedTemplateReasonsForTaskLabels($labels);

        $pamongTemplateKey = self::pamongTemplateKeyForGuruTendik($guruTendik);

        if ($pamongTemplateKey !== null) {
            $reasonMap[$pamongTemplateKey] ??= [];
            array_unshift($reasonMap[$pamongTemplateKey], 'Jenis PTK Pamong');
            $reasonMap[$pamongTemplateKey] = collect($reasonMap[$pamongTemplateKey])
                ->unique()
                ->values()
                ->all();
        }

        return static::$suggestedTemplateReasonsForGuruCache[$cacheKey] = $reasonMap;
    }

    public static function pamongTemplateKeyForGuruTendik(?GuruTendik $guruTendik): ?string
    {
        if (strcasecmp((string) $guruTendik?->jenis_ptk, 'Pamong') !== 0) {
            return null;
        }

        return match (strtoupper((string) $guruTendik?->jk)) {
            'L' => 'pamong_putra',
            'P' => 'pamong_putri',
            default => null,
        };
    }

    /**
     * @param  array<int, string>  $templateKeys
     * @return array<int, string>
     */
    public static function boardingPamongRoleNamesForTemplates(array $templateKeys): array
    {
        $templateKeys = self::normalizeTemplateKeys($templateKeys);

        return collect($templateKeys)
            ->filter(fn (string $key): bool => in_array($key, ['pamong_putra', 'pamong_putri'], true))
            ->flatMap(fn (string $key): array => self::definitions()[$key]['roles'] ?? [])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function suggestedTemplatesForGuruTendik(?GuruTendik $guruTendik): array
    {
        return collect(self::suggestedTemplateReasonsForGuruTendik($guruTendik))
            ->keys()
            ->values()
            ->all();
    }

    public static function suggestionSummary(array $keys): string
    {
        $labels = collect($keys)
            ->map(fn (string $key): ?string => self::definitions()[$key]['label'] ?? null)
            ->filter()
            ->values();

        return $labels->isEmpty()
            ? 'Belum ada saran akses dari histori tugas tambahan.'
            : 'Saran akses dari histori tugas tambahan: '.$labels->implode(', ').'.';
    }

    /**
     * @param  array<string, array<int, string>>  $reasonMap
     */
    public static function suggestionReasonSummary(array $reasonMap): string
    {
        if ($reasonMap === []) {
            return 'Belum ada saran akses dari histori tugas tambahan.';
        }

        $parts = collect($reasonMap)
            ->map(function (array $matchedLabels, string $templateKey): ?string {
                $templateLabel = self::definitions()[$templateKey]['label'] ?? null;

                if ($templateLabel === null) {
                    return null;
                }

                return $templateLabel.' <- '.collect($matchedLabels)->implode(', ');
            })
            ->filter()
            ->values()
            ->all();

        return $parts === []
            ? 'Belum ada saran akses dari histori tugas tambahan.'
            : 'Saran akses dari histori tugas tambahan: '.implode('; ', $parts).'.';
    }

    public static function suggestedTemplateReasonSummaryForGuruTendik(?GuruTendik $guruTendik): string
    {
        if (! $guruTendik?->exists) {
            return 'Belum ada saran akses dari histori tugas tambahan.';
        }

        $cacheKey = (int) $guruTendik->getKey();

        if (array_key_exists($cacheKey, static::$suggestedTemplateReasonSummaryForGuruCache)) {
            return static::$suggestedTemplateReasonSummaryForGuruCache[$cacheKey];
        }

        return static::$suggestedTemplateReasonSummaryForGuruCache[$cacheKey] = static::suggestionReasonSummary(
            static::suggestedTemplateReasonsForGuruTendik($guruTendik)
        );
    }

    /**
     * @param  array<string, string>  $levels
     * @return array<int, string>
     */
    public static function matchedTemplateKeysForLevels(array $levels): array
    {
        $normalizedLevels = AdminModuleAccess::normalizeLevels($levels);

        return collect(self::definitions())
            ->filter(function (array $definition) use ($normalizedLevels): bool {
                if (($definition['manage'] ?? []) === [] && ($definition['view'] ?? []) === []) {
                    return false;
                }

                foreach ($definition['manage'] ?? [] as $prefix) {
                    if (! self::levelSatisfies($normalizedLevels[$prefix] ?? AdminModuleAccess::NONE, AdminModuleAccess::MANAGE)) {
                        return false;
                    }
                }

                foreach ($definition['view'] ?? [] as $prefix) {
                    if (! self::levelSatisfies($normalizedLevels[$prefix] ?? AdminModuleAccess::NONE, AdminModuleAccess::VIEW)) {
                        return false;
                    }
                }

                return true;
            })
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $templateKeys
     * @return array<int, string>
     */
    public static function affectedPrefixesForTemplates(array $templateKeys): array
    {
        return collect($templateKeys)
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => array_key_exists($value, self::definitions()))
            ->flatMap(function (string $templateKey): array {
                $definition = self::definitions()[$templateKey];

                return array_values(array_unique(array_merge(
                    $definition['manage'] ?? [],
                    $definition['view'] ?? [],
                )));
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function matchedTemplateKeysForUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        if ($user->hasRole('admin')) {
            return [];
        }

        $cacheKey = static::userTemplateCacheKey($user);

        if (array_key_exists($cacheKey, static::$matchedTemplateKeysForUserCache)) {
            return static::$matchedTemplateKeysForUserCache[$cacheKey];
        }

        return static::$matchedTemplateKeysForUserCache[$cacheKey] = self::matchedTemplateKeysForLevels(AdminModuleAccess::effectiveLevels($user));
    }

    /**
     * @return array<int, string>
     */
    public static function matchedTemplateLabelsForUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        if ($user->hasRole('admin')) {
            return ['Admin Penuh'];
        }

        $cacheKey = static::userTemplateCacheKey($user);

        if (array_key_exists($cacheKey, static::$matchedTemplateLabelsForUserCache)) {
            return static::$matchedTemplateLabelsForUserCache[$cacheKey];
        }

        return static::$matchedTemplateLabelsForUserCache[$cacheKey] = collect(self::matchedTemplateKeysForUser($user))
            ->map(fn (string $key): ?string => self::definitions()[$key]['label'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    protected static function userTemplateCacheKey(User $user): string
    {
        return implode(':', [
            (string) ($user->getKey() ?? 'guest'),
            $user->hasRole('admin') ? 'admin' : 'user',
            md5(json_encode($user->module_access_levels ?? [])),
        ]);
    }

    /**
     * @param  array<int, string>  $templateKeys
     */
    public static function applyTemplateFilterToQuery(Builder $query, array $templateKeys, string $column = 'module_access_levels'): Builder
    {
        $templateKeys = self::normalizeTemplateKeys($templateKeys);

        if ($templateKeys === []) {
            return $query;
        }

        return $query->where(function (Builder $outerQuery) use ($templateKeys, $column): void {
            foreach ($templateKeys as $templateKey) {
                $definition = self::definitions()[$templateKey];

                $outerQuery->orWhere(function (Builder $templateQuery) use ($definition, $column): void {
                    foreach ($definition['manage'] ?? [] as $prefix) {
                        $templateQuery->where("{$column}->{$prefix}", AdminModuleAccess::MANAGE);
                    }

                    foreach ($definition['view'] ?? [] as $prefix) {
                        $templateQuery->where(function (Builder $levelQuery) use ($column, $prefix): void {
                            $levelQuery
                                ->where("{$column}->{$prefix}", AdminModuleAccess::VIEW)
                                ->orWhere("{$column}->{$prefix}", AdminModuleAccess::MANAGE);
                        });
                    }
                });
            }
        });
    }

    /**
     * @param  array<string, string>  $levels
     * @param  array<int, string>  $templateKeys
     * @return array<string, string>
     */
    public static function removeTemplatesFromLevels(array $levels, array $templateKeys): array
    {
        $normalizedLevels = AdminModuleAccess::normalizeLevels($levels);
        $templateKeys = self::normalizeTemplateKeys($templateKeys);

        if ($templateKeys === []) {
            return $normalizedLevels;
        }

        $currentTemplateKeys = self::matchedTemplateKeysForLevels($normalizedLevels);
        $remainingTemplateKeys = collect($currentTemplateKeys)
            ->reject(fn (string $value): bool => in_array($value, $templateKeys, true))
            ->values()
            ->all();
        $remainingLevels = self::mergedLevelsForTemplates($remainingTemplateKeys);
        $affectedPrefixes = self::affectedPrefixesForTemplates($templateKeys);

        foreach ($affectedPrefixes as $prefix) {
            $normalizedLevels[$prefix] = $remainingLevels[$prefix] ?? AdminModuleAccess::NONE;
        }

        return AdminModuleAccess::normalizeLevels($normalizedLevels);
    }

    /**
     * @return array{roles:array<int, int|string>,module_access_levels:array<string, string>,allowed_navigation_items:array<int, string>}|null
     */
    public static function formState(?string $key): ?array
    {
        if (! $key || ! array_key_exists($key, self::definitions())) {
            return null;
        }

        $definition = self::definitions()[$key];
        $levels = AdminModuleAccess::normalizeLevels([]);

        foreach ($definition['view'] as $prefix) {
            if (array_key_exists($prefix, $levels)) {
                $levels[$prefix] = AdminModuleAccess::VIEW;
            }
        }

        foreach ($definition['manage'] as $prefix) {
            if (array_key_exists($prefix, $levels)) {
                $levels[$prefix] = AdminModuleAccess::MANAGE;
            }
        }

        $roleIds = Role::query()
            ->whereIn('name', $definition['roles'])
            ->pluck('id')
            ->all();

        return [
            'roles' => $roleIds,
            'module_access_levels' => $levels,
            'allowed_navigation_items' => $definition['allowed_navigation_items'] ?? [],
        ];
    }

    /**
     * @return array<int, int|string>
     */
    public static function defaultRoleIdsForTemplate(?string $key): array
    {
        return self::formState($key)['roles'] ?? [];
    }

    /**
     * @return array<string, string>
     */
    public static function defaultLevelsForTemplate(?string $key): array
    {
        return self::formState($key)['module_access_levels'] ?? AdminModuleAccess::normalizeLevels([]);
    }

    protected static function maxLevel(string $left, string $right): string
    {
        $weights = [
            AdminModuleAccess::NONE => 0,
            AdminModuleAccess::VIEW => 1,
            AdminModuleAccess::MANAGE => 2,
        ];

        return ($weights[$right] ?? 0) > ($weights[$left] ?? 0)
            ? $right
            : $left;
    }

    protected static function levelSatisfies(string $actual, string $required): bool
    {
        $weights = [
            AdminModuleAccess::NONE => 0,
            AdminModuleAccess::VIEW => 1,
            AdminModuleAccess::MANAGE => 2,
        ];

        return ($weights[$actual] ?? 0) >= ($weights[$required] ?? 0);
    }
}
