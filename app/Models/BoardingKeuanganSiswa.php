<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoardingStudent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class BoardingKeuanganSiswa extends Model
{
    use BelongsToBoardingStudent;

    protected static ?string $pamongOwnershipColumn = 'pamong_user_id';

    protected static array $syncedUserIds = [];

    protected static ?bool $tableAvailable = null;

    protected static ?bool $pamongUserColumnAvailableCache = null;

    protected static array $pamongNamaOptionsCache = [];

    protected $table = 'boarding_keuangan_siswas';

    protected $guarded = [];

    protected $casts = [
        'pamong_user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if (static::pamongUserColumnAvailable() && blank($record->pamong_user_id) && auth()->user()?->isBoardingPamong()) {
                $record->pamong_user_id = auth()->id();
            }

            if (static::pamongUserColumnAvailable() && $record->pamong_user_id) {
                $record->pamong_nama = User::query()->whereKey($record->pamong_user_id)->value('name') ?: $record->pamong_nama;
            }
        });
    }

    public static function syncVisibleStudentRecordsForUser(mixed $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        $userKey = (string) ($user->getKey() ?? spl_object_id($user));

        if (isset(static::$syncedUserIds[$userKey])) {
            return;
        }

        static::$syncedUserIds[$userKey] = true;

        $visibleStudents = DataSiswa::applyVisibleScope(
            DataSiswa::query()
                ->select(['id', 'jk', 'rombel_saat_ini'])
                ->where('status', 'aktif'),
            $user,
        )->get();

        if ($visibleStudents->isEmpty()) {
            return;
        }

        $existingRecords = static::query()
            ->whereIn('siswa_id', $visibleStudents->pluck('id'))
            ->get(['id', 'siswa_id', 'pamong_user_id', 'pamong_nama', 'angkatan_label', 'kategori_asrama'])
            ->keyBy('siswa_id');

        $now = now();
        $inserts = [];

        foreach ($visibleStudents as $student) {
            /** @var self|null $record */
            $record = $existingRecords->get($student->getKey());

            if (
                static::pamongUserColumnAvailable()
                && $user->isBoardingPamong()
                && $record?->exists
                && filled($record->pamong_user_id)
                && (int) $record->pamong_user_id !== (int) $user->getKey()
            ) {
                continue;
            }

            if (! $record) {
                $insert = [
                    'siswa_id' => $student->getKey(),
                    'pamong_nama' => $user->isBoardingPamong() ? $user->name : null,
                    'angkatan_label' => $student->rombel_saat_ini,
                    'kategori_asrama' => static::resolveKategoriAsrama($student),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (static::pamongUserColumnAvailable() && $user->isBoardingPamong()) {
                    $insert['pamong_user_id'] = $user->getKey();
                }

                $inserts[] = $insert;

                continue;
            }

            $updates = [];

            if (blank($record->angkatan_label) && filled($student->rombel_saat_ini)) {
                $updates['angkatan_label'] = $student->rombel_saat_ini;
            }

            if (blank($record->kategori_asrama)) {
                $updates['kategori_asrama'] = static::resolveKategoriAsrama($student);
            }

            if ($user->isBoardingPamong()) {
                if (static::pamongUserColumnAvailable() && blank($record->pamong_user_id)) {
                    $updates['pamong_user_id'] = $user->getKey();
                }

                if (blank($record->pamong_nama)) {
                    $updates['pamong_nama'] = $user->name;
                }
            }

            if ($updates !== []) {
                $updates['updated_at'] = $now;

                static::query()
                    ->whereKey($record->getKey())
                    ->update($updates);
            }
        }

        foreach (array_chunk($inserts, 200) as $chunk) {
            static::query()->insert($chunk);
        }
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(DataSiswa::class, 'siswa_id');
    }

    public function pamongUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pamong_user_id');
    }

    public function transaksis(): HasMany
    {
        return $this->hasMany(BoardingKeuanganTransaksi::class, 'boarding_keuangan_siswa_id');
    }

    public static function resolveKategoriAsrama(?DataSiswa $student): ?string
    {
        return match ($student?->jk) {
            'L' => 'putra',
            'P' => 'putri',
            default => null,
        };
    }

    public function getTotalTitipanAttribute(): int
    {
        if (array_key_exists('titipan_total', $this->attributes) && $this->attributes['titipan_total'] !== null) {
            return (int) $this->attributes['titipan_total'];
        }

        return (int) $this->transaksis()
            ->forSummaryBucket('titipan')
            ->sum('nominal');
    }

    public function getTotalPemberianAttribute(): int
    {
        if (array_key_exists('pemberian_total', $this->attributes) && $this->attributes['pemberian_total'] !== null) {
            return (int) $this->attributes['pemberian_total'];
        }

        return (int) $this->transaksis()
            ->forSummaryBucket('pemberian')
            ->sum('nominal');
    }

    public function getTotalKasAttribute(): int
    {
        if (array_key_exists('kas_total', $this->attributes) && $this->attributes['kas_total'] !== null) {
            return (int) $this->attributes['kas_total'];
        }

        return (int) $this->transaksis()
            ->forSummaryBucket('kas')
            ->sum('nominal');
    }

    public function getTotalKeluarAttribute(): int
    {
        return $this->total_pemberian + $this->total_kas;
    }

    public function getSaldoTitipanAttribute(): int
    {
        return $this->total_titipan - $this->total_keluar;
    }

    public function getSaldoTersisaAttribute(): int
    {
        return $this->saldo_titipan;
    }

    public function getTotalKategoriCustomAttribute(): int
    {
        if (array_key_exists('custom_total', $this->attributes) && $this->attributes['custom_total'] !== null) {
            return (int) $this->attributes['custom_total'];
        }

        if (! BoardingKeuanganTransaksi::kategoriRelationAvailable()) {
            return 0;
        }

        return (int) $this->transaksis()
            ->whereHas('kategori', fn ($query) => $query->where('is_system', false))
            ->sum('nominal');
    }

    protected static function pamongOwnershipColumn(): ?string
    {
        if (! static::pamongUserColumnAvailable()) {
            return null;
        }

        return static::$pamongOwnershipColumn;
    }

    public static function pamongUserColumnAvailable(): bool
    {
        if (static::$pamongUserColumnAvailableCache !== null) {
            return static::$pamongUserColumnAvailableCache;
        }

        $table = (new static)->getTable();

        if (! static::tableAvailable($table)) {
            return static::$pamongUserColumnAvailableCache = false;
        }

        return static::$pamongUserColumnAvailableCache = SchemaFacade::hasColumn($table, 'pamong_user_id');
    }

    public static function flushRuntimeSchemaCache(): void
    {
        static::$tableAvailable = null;
        static::$pamongUserColumnAvailableCache = null;
        static::$pamongNamaOptionsCache = [];
    }

    public static function formatRupiah(int $nominal): string
    {
        $prefix = $nominal < 0 ? '-Rp. ' : 'Rp. ';

        return $prefix.number_format(abs($nominal), 0, ',', '.');
    }

    public static function pamongNamaOptions(mixed $user): array
    {
        $userKey = $user instanceof User
            ? (string) $user->getKey()
            : serialize($user);

        if (array_key_exists($userKey, static::$pamongNamaOptionsCache)) {
            return static::$pamongNamaOptionsCache[$userKey];
        }

        return static::$pamongNamaOptionsCache[$userKey] = static::query()
            ->visibleToUser($user)
            ->whereNotNull('pamong_nama')
            ->where('pamong_nama', '!=', '')
            ->orderBy('pamong_nama')
            ->pluck('pamong_nama', 'pamong_nama')
            ->toArray();
    }

    protected static function tableAvailable(string $table): bool
    {
        return static::$tableAvailable ??= SchemaFacade::hasTable($table);
    }
}
