<?php

namespace App\Support\Assessment\Reporting;

use App\Exceptions\AssessmentReportRenderBusy;
use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

final class AssessmentReportRenderGate
{
    public function run(Closure $callback): mixed
    {
        $lock = $this->acquire();

        if (! $lock) {
            throw new AssessmentReportRenderBusy(
                max(5, (int) config('assessment.reports.render.retry_after_seconds', 10)),
            );
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    private function acquire(): ?Lock
    {
        $slots = max(1, (int) config('assessment.reports.render.active_slots', 1));
        $ttl = max(30, (int) config('assessment.reports.render.lock_seconds', 180));

        for ($slot = 1; $slot <= $slots; $slot++) {
            $lock = Cache::lock('assessment-report-render-slot-'.$slot, $ttl);

            if ($lock->get()) {
                return $lock;
            }
        }

        return null;
    }
}
