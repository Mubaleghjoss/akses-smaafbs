<?php

namespace App\Console\Commands;

use App\Enums\Assessment\AssessmentType;
use App\Models\Assessment\AssessmentScheme;
use App\Support\Assessment\AstsSchemeComponents;
use Illuminate\Console\Command;

class RepairAstsSchemeComponents extends Command
{
    protected $signature = 'assessment:repair-asts-components
        {--period= : ID periode ASTS yang akan diperbaiki}
        {--dry-run : Tampilkan skema yang perlu diperbaiki tanpa mengubah data}';

    protected $description = 'Menormalkan skema ASTS tanpa nilai menjadi 3 Ujian Harian dan Nilai Murni ASTS';

    public function handle(AstsSchemeComponents $components): int
    {
        $query = AssessmentScheme::query()
            ->whereHas('period', fn ($periods) => $periods->where('type', AssessmentType::ASTS->value))
            ->with(['period', 'components']);

        if ($periodId = $this->option('period')) {
            $query->where('assessment_period_id', (int) $periodId);
        }

        $updated = 0;
        $skipped = 0;
        foreach ($query->cursor() as $scheme) {
            $scheme->load('components');
            if ($components->isStandard($scheme)) {
                continue;
            }

            if (! $components->canNormalize($scheme)) {
                $skipped++;
                $this->warn("Dilewati: skema #{$scheme->id} ({$scheme->name}) sudah memiliki nilai.");

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("Akan diperbaiki: skema #{$scheme->id} ({$scheme->name}).");

                continue;
            }

            $components->normalize($scheme);
            $updated++;
            $this->info("Diperbaiki: skema #{$scheme->id} ({$scheme->name}).");
        }

        $this->newLine();
        $this->info("Selesai. {$updated} skema diperbaiki; {$skipped} skema bernilai dilewati.");
        if ($skipped > 0) {
            $this->warn('Skema yang sudah bernilai tidak diubah agar nilai historis tidak terpengaruh. Tinjau dan perbaiki melalui Komponen & Bobot bila diperlukan.');
        }

        return self::SUCCESS;
    }
}
