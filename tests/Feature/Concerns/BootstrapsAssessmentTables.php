<?php

namespace Tests\Feature\Concerns;

use Illuminate\Support\Facades\Schema;

trait BootstrapsAssessmentTables
{
    protected function bootstrapAssessmentTables(): void
    {
        if (Schema::hasTable('assessment_report_templates')) {
            return;
        }

        (require database_path(
            'migrations/2026_07_31_080000_create_assessment_foundation_tables.php'
        ))->up();

        (require database_path(
            'migrations/2026_07_31_120000_extend_assessment_report_structure.php'
        ))->up();

        (require database_path(
            'migrations/2026_08_06_150000_add_assessment_subject_categories.php'
        ))->up();

        (require database_path(
            'migrations/2026_07_31_190000_add_assessment_report_generation_runs.php'
        ))->up();

        (require database_path(
            'migrations/2026_08_03_080000_add_stream_delivery_to_assessment_reports.php'
        ))->up();
    }
}
