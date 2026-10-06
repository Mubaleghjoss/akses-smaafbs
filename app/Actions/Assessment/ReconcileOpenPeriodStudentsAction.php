<?php

namespace App\Actions\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\DataSiswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Removes students who have left the school from editable, open-period lists.
 * Snapshots and any scores are retained for audit and historic reporting.
 */
final class ReconcileOpenPeriodStudentsAction
{
    public function forOpenPeriodsOfType(AssessmentType $type): int
    {
        if (! Schema::hasTable('data_siswa')) {
            return 0;
        }

        return AssessmentPeriod::query()
            ->where('type', $type->value)
            ->where('status', AssessmentPeriodStatus::OPEN->value)
            ->pluck('id')
            ->sum(fn (int $periodId): int => $this->forPeriod($periodId));
    }

    public function forPeriod(int $periodId): int
    {
        if (! Schema::hasTable('data_siswa')) {
            return 0;
        }

        return DB::transaction(function () use ($periodId): int {
            $period = AssessmentPeriod::query()->lockForUpdate()->find($periodId);

            if (! $period || $period->status !== AssessmentPeriodStatus::OPEN) {
                return 0;
            }

            $snapshots = AssessmentPeriodStudent::query()
                ->where('assessment_period_id', $period->getKey())
                ->where('is_active', true)
                ->lockForUpdate()
                ->get(['id', 'student_id']);

            if ($snapshots->isEmpty()) {
                return 0;
            }

            $students = DataSiswa::query()
                ->whereIn('id', $snapshots->pluck('student_id'))
                ->get(['id', 'status', 'rombel_saat_ini'])
                ->keyBy('id');

            $snapshotIds = $snapshots
                ->filter(function (AssessmentPeriodStudent $snapshot) use ($students): bool {
                    $student = $students->get($snapshot->student_id);

                    // A missing source record is retained rather than guessed inactive.
                    return $student
                        && (
                            strtolower(trim((string) $student->status)) !== 'aktif'
                            || Str::contains(
                                Str::upper(trim((string) $student->rombel_saat_ini)),
                                'MUTASI',
                            )
                        );
                })
                ->pluck('id');

            if ($snapshotIds->isEmpty()) {
                return 0;
            }

            return AssessmentPeriodStudent::query()
                ->whereIn('id', $snapshotIds)
                ->update(['is_active' => false]);
        }, 3);
    }
}
