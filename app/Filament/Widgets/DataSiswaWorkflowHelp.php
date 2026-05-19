<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DataSiswaResource;
use App\Models\DataSiswa;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Filament\Widgets\Widget;

class DataSiswaWorkflowHelp extends Widget
{
    protected string $view = 'filament.widgets.data-siswa-workflow-help';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getViewData(): array
    {
        return [
            'dataTesSummary' => $this->dataTesSummary(),
            'currentDataTesStatus' => request()->query('data_tes_status'),
            'filterUrls' => [
                'filled' => DataSiswaResource::getUrl('index', ['data_tes_status' => 'filled']),
                'missing' => DataSiswaResource::getUrl('index', ['data_tes_status' => 'missing']),
                'all' => DataSiswaResource::getUrl('index'),
            ],
            'templateUrl' => route('admin.data-siswa.import-template', absolute: false),
            'completionStatus' => $this->completionStatus(),
        ];
    }

    /**
     * @return array{label:string, classes:string}
     */
    protected function completionStatus(): array
    {
        $percentage = (int) ($this->dataTesSummary()['completion_percentage'] ?? 0);

        return match (true) {
            $percentage >= 85 => [
                'label' => 'LENGKAP',
                'classes' => 'border-emerald-300 bg-emerald-50 text-emerald-800',
            ],
            $percentage >= 50 => [
                'label' => 'PERLU DILENGKAPI',
                'classes' => 'border-amber-300 bg-amber-50 text-amber-800',
            ],
            default => [
                'label' => 'PRIORITAS IMPORT',
                'classes' => 'border-rose-300 bg-rose-50 text-rose-800',
            ],
        };
    }

    /**
     * @return array{filled:int, missing:int, total:int, completion_percentage:int}
     */
    protected function dataTesSummary(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return ['filled' => 0, 'missing' => 0, 'total' => 0, 'completion_percentage' => 0];
        }

        $cacheKey = 'data_siswa_workflow_help:data_tes_summary:'.sha1(json_encode([
            'id' => $user->getKey(),
            'roles' => $user->relationLoaded('roles')
                ? $user->roles->pluck('name')->values()->all()
                : $user->getRoleNames()->values()->all(),
            'boarding_rombel_scope' => $user->boardingRombelScopes(),
            'guru_walas_scope' => $user->guruWalasScopes(),
            'angkatan_scope' => $user->boardingAngkatanScope(),
            'guru_tendik_id' => $user->guru_tendik_id,
        ]));

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user): array {
            $query = DataSiswa::applyVisibleScope(DataSiswa::query(), $user);

            $filled = (clone $query)
                ->where(function ($subQuery): void {
                    $subQuery
                        ->whereNotNull('kepribadian')->where('kepribadian', '!=', '')
                        ->orWhere(fn ($query) => $query->whereNotNull('gaya_belajar')->where('gaya_belajar', '!=', ''))
                        ->orWhere(fn ($query) => $query->whereNotNull('profiling')->where('profiling', '!=', ''))
                        ->orWhere(fn ($query) => $query->whereNotNull('mbti')->where('mbti', '!=', ''));
                })
                ->count();

            $missing = (clone $query)
                ->where(fn ($subQuery) => $subQuery->whereNull('kepribadian')->orWhere('kepribadian', ''))
                ->where(fn ($subQuery) => $subQuery->whereNull('gaya_belajar')->orWhere('gaya_belajar', ''))
                ->where(fn ($subQuery) => $subQuery->whereNull('profiling')->orWhere('profiling', ''))
                ->where(fn ($subQuery) => $subQuery->whereNull('mbti')->orWhere('mbti', ''))
                ->count();

            $total = (int) $filled + (int) $missing;
            $completionPercentage = $total > 0
                ? (int) round((((int) $filled / $total) * 100))
                : 0;

            return [
                'filled' => (int) $filled,
                'missing' => (int) $missing,
                'total' => $total,
                'completion_percentage' => max(0, min(100, $completionPercentage)),
            ];
        });
    }
}
