<?php

namespace App\Models;

use App\Filament\Resources\BerkasGuruResource;
use App\Support\Admin\Dashboard\DashboardCacheSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use App\Support\GoogleDrive\GoogleDriveService;

class GuruTendikTugasTambahan extends Model
{
    protected $table = 'guru_tendik_tugas_tambahans';

    protected $guarded = [];

    protected $casts = [
        'tmt' => 'date',
        'tst' => 'date',
        'berkas_guru_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $record): void {
            $record->syncBerkasGuruRecord();
            DashboardCacheSupport::forgetModule('guru_tendik');
            DashboardCacheSupport::forgetModule('google_drive_monitor');
        });

        static::deleted(function (self $record): void {
            DashboardCacheSupport::forgetModule('guru_tendik');
            DashboardCacheSupport::forgetModule('google_drive_monitor');
        });
    }

    public function guruTendik(): BelongsTo
    {
        return $this->belongsTo(GuruTendik::class, 'guru_tendik_id');
    }

    public function berkasGuru(): BelongsTo
    {
        return $this->belongsTo(BerkasGuru::class, 'berkas_guru_id');
    }

    public function syncBerkasGuruRecord(): void
    {
        if (! $this->guru_tendik_id || blank($this->sk_file_path)) {
            return;
        }

        $jenisBerkas = JenisBerkas::query()->firstOrCreate(
            ['nama_berkas' => 'SK Tugas Tambahan'],
            [
                'deskripsi' => 'Dokumen SK untuk penugasan tambahan guru / tendik.',
                'wajib' => 'tidak',
                'urutan' => 90,
                'status' => 'aktif',
            ],
        );

        $berkas = BerkasGuru::query()->updateOrCreate(
            ['id' => $this->berkas_guru_id],
            [
                'guru_id' => $this->guru_tendik_id,
                'jenis_berkas_id' => $jenisBerkas->id,
                'file_path' => $this->sk_file_path,
                'keterangan' => 'SK '.$this->tugas_tambahan.' / '.$this->no_sk,
                'uploaded_at' => now(),
                'has_deleted' => 0,
            ],
        );

        if ((int) $this->berkas_guru_id !== (int) $berkas->id) {
            $this->forceFill(['berkas_guru_id' => $berkas->id])->saveQuietly();
        }

        if (BerkasGuruResource::normalizeRecord($berkas)) {
            $berkas->refresh();
            $this->forceFill(['sk_file_path' => $berkas->file_path])->saveQuietly();
        }

        if (Schema::hasTable('pengaturan')) {
            app(GoogleDriveService::class)->queueBerkasGuruSync($berkas->fresh());
        }
    }

    public function resolvedSkUrl(): ?string
    {
        $path = trim((string) $this->sk_file_path);

        return $path !== '' ? Storage::disk('public')->url($path) : null;
    }
}


