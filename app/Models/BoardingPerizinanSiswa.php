<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoardingStudent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class BoardingPerizinanSiswa extends Model
{
    use BelongsToBoardingStudent;

    protected static ?bool $diizinkanOlehUserColumnAvailable = null;

    protected static ?bool $diizinkanOlehNamaColumnAvailable = null;

    protected static ?bool $dibuatOlehColumnAvailable = null;

    protected static ?bool $pamongUserColumnAvailable = null;

    protected static ?bool $legacyPickupTimeColumnAvailable = null;

    protected static ?bool $legacyReturnNoteColumnAvailable = null;

    protected static ?bool $diprosesOlehColumnAvailable = null;

    protected static ?bool $statusPerizinanColumnAvailable = null;

    /**
     * @var array<string, bool>
     */
    protected static array $runtimeColumnAvailability = [];

    protected $table = 'boarding_perizinan_siswas';

    protected $guarded = [];

    protected $casts = [
        'tanggal_izin' => 'date',
        'tanggal_kembali' => 'date',
        'pamong_user_id' => 'integer',
        'diizinkan_oleh_user_id' => 'integer',
        'dibuat_oleh' => 'integer',
        'diproses_oleh' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record): void {
            if (static::pamongUserColumnAvailable() && blank($record->pamong_user_id) && auth()->user()?->isBoardingPamong()) {
                $record->pamong_user_id = auth()->id();
            }

            if (static::dibuatOlehColumnAvailable() && blank($record->dibuat_oleh) && auth()->check()) {
                $record->dibuat_oleh = auth()->id();
            }

            if (static::legacyPickupTimeColumnAvailable() && filled($record->waktu_izin) && blank($record->getAttributeFromArray('waktu_jemput'))) {
                $record->setAttribute('waktu_jemput', $record->waktu_izin);
            }

            if (static::legacyReturnNoteColumnAvailable() && filled($record->detail_kembali) && blank($record->getAttributeFromArray('catatan_kembali'))) {
                $record->setAttribute('catatan_kembali', $record->detail_kembali);
            }

            if (static::approvalUserColumnAvailable() && filled($record->diizinkan_oleh_user_id)) {
                $record->diizinkan_oleh_nama = null;
            }

            if (static::approvalNameColumnAvailable() && filled($record->diizinkan_oleh_nama)) {
                $record->diizinkan_oleh_user_id = null;
                $record->diizinkan_oleh_nama = trim((string) $record->diizinkan_oleh_nama);
            }

            if (filled($record->tanggal_kembali)) {
                if (static::statusPerizinanColumnAvailable()) {
                    $record->status_perizinan = 'selesai';
                }

                if (static::diprosesOlehColumnAvailable() && blank($record->diproses_oleh) && auth()->check()) {
                    $record->diproses_oleh = auth()->id();
                }
            } elseif (static::statusPerizinanColumnAvailable() && blank($record->status_perizinan)) {
                $record->status_perizinan = 'pending';
            }
        });
    }

    public function scopeVisibleToUser(Builder $query, mixed $user): Builder
    {
        if (! $user instanceof User || ! $user->isBoardingPamong()) {
            return $query;
        }

        $query->whereHas('siswa', function (Builder $studentQuery) use ($user): void {
            DataSiswa::applyVisibleScope($studentQuery, $user);
        });

        if ($ownershipColumn = static::resolvePamongOwnershipColumn()) {
            $query->where($ownershipColumn, $user->getKey());
        }

        return $query;
    }

    protected static function dibuatOlehColumnAvailable(): bool
    {
        return static::$dibuatOlehColumnAvailable ??= SchemaFacade::hasTable((new static)->getTable())
            && SchemaFacade::hasColumn((new static)->getTable(), 'dibuat_oleh');
    }

    public static function approvalUserColumnAvailable(): bool
    {
        return static::$diizinkanOlehUserColumnAvailable ??= SchemaFacade::hasTable((new static)->getTable())
            && SchemaFacade::hasColumn((new static)->getTable(), 'diizinkan_oleh_user_id');
    }

    public static function approvalNameColumnAvailable(): bool
    {
        return static::$diizinkanOlehNamaColumnAvailable ??= SchemaFacade::hasTable((new static)->getTable())
            && SchemaFacade::hasColumn((new static)->getTable(), 'diizinkan_oleh_nama');
    }

    protected static function pamongUserColumnAvailable(): bool
    {
        return static::$pamongUserColumnAvailable ??= SchemaFacade::hasTable((new static)->getTable())
            && SchemaFacade::hasColumn((new static)->getTable(), 'pamong_user_id');
    }

    protected static function legacyPickupTimeColumnAvailable(): bool
    {
        return static::$legacyPickupTimeColumnAvailable ??= SchemaFacade::hasTable((new static)->getTable())
            && SchemaFacade::hasColumn((new static)->getTable(), 'waktu_jemput');
    }

    protected static function legacyReturnNoteColumnAvailable(): bool
    {
        return static::$legacyReturnNoteColumnAvailable ??= SchemaFacade::hasTable((new static)->getTable())
            && SchemaFacade::hasColumn((new static)->getTable(), 'catatan_kembali');
    }

    protected static function diprosesOlehColumnAvailable(): bool
    {
        return static::$diprosesOlehColumnAvailable ??= SchemaFacade::hasTable((new static)->getTable())
            && SchemaFacade::hasColumn((new static)->getTable(), 'diproses_oleh');
    }

    public static function statusPerizinanColumnAvailable(): bool
    {
        return static::$statusPerizinanColumnAvailable ??= SchemaFacade::hasTable((new static)->getTable())
            && SchemaFacade::hasColumn((new static)->getTable(), 'status_perizinan');
    }

    public static function returnFieldColumnAvailable(string $column): bool
    {
        return static::$runtimeColumnAvailability[$column] ??= SchemaFacade::hasTable((new static)->getTable())
            && SchemaFacade::hasColumn((new static)->getTable(), $column);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function buildReturnCompletionPayload(array $data): array
    {
        $payload = [];

        if (static::returnFieldColumnAvailable('tanggal_kembali')) {
            $payload['tanggal_kembali'] = $data['tanggal_kembali'] ?? null;
        }

        if (static::returnFieldColumnAvailable('waktu_kembali')) {
            $payload['waktu_kembali'] = $data['waktu_kembali'] ?? null;
        }

        if (static::returnFieldColumnAvailable('detail_kembali')) {
            $payload['detail_kembali'] = $data['detail_kembali'] ?? null;
        } elseif (static::legacyReturnNoteColumnAvailable()) {
            $payload['catatan_kembali'] = $data['detail_kembali'] ?? null;
        }

        if (static::returnFieldColumnAvailable('kafaroh_keterlambatan')) {
            $payload['kafaroh_keterlambatan'] = $data['kafaroh_keterlambatan'] ?? null;
        }

        if (static::statusPerizinanColumnAvailable()) {
            $payload['status_perizinan'] = filled($data['tanggal_kembali'] ?? null) ? 'selesai' : 'pending';
        }

        if (static::diprosesOlehColumnExists() && auth()->check()) {
            $payload['diproses_oleh'] = auth()->id();
        }

        return $payload;
    }

    public static function diprosesOlehColumnExists(): bool
    {
        return static::diprosesOlehColumnAvailable();
    }

    public static function resolvePamongOwnershipColumn(): ?string
    {
        if (static::pamongUserColumnAvailable()) {
            return 'pamong_user_id';
        }

        if (static::dibuatOlehColumnAvailable()) {
            return 'dibuat_oleh';
        }

        return null;
    }

    public static function flushRuntimeSchemaCache(): void
    {
        static::$diizinkanOlehUserColumnAvailable = null;
        static::$diizinkanOlehNamaColumnAvailable = null;
        static::$dibuatOlehColumnAvailable = null;
        static::$pamongUserColumnAvailable = null;
        static::$legacyPickupTimeColumnAvailable = null;
        static::$legacyReturnNoteColumnAvailable = null;
        static::$diprosesOlehColumnAvailable = null;
        static::$statusPerizinanColumnAvailable = null;
        static::$runtimeColumnAvailability = [];
    }

    public function getStatusPerizinanAttribute(?string $value): string
    {
        if (filled($value)) {
            return $value;
        }

        return filled($this->tanggal_kembali) ? 'selesai' : 'pending';
    }

    public function getWaktuIzinAttribute(?string $value): ?string
    {
        return filled($value)
            ? $value
            : $this->getAttributeFromArray('waktu_jemput');
    }

    public function getDetailKembaliAttribute(?string $value): ?string
    {
        return filled($value)
            ? $value
            : $this->getAttributeFromArray('catatan_kembali');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(DataSiswa::class, 'siswa_id');
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function diprosesOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    public function diizinkanOlehUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diizinkan_oleh_user_id');
    }
}
