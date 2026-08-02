<?php

namespace App\Filament\Widgets;

use App\Enums\Assessment\AssessmentType;
use App\Models\Assessment\ReportTemplate;
use Filament\Widgets\Widget;

class AssessmentReportTemplateGuide extends Widget
{
    protected string $view = 'filament.widgets.assessment-report-template-guide';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'primaryAsts' => ReportTemplate::query()
                ->where('type', AssessmentType::ASTS->value)
                ->where('is_active', true)
                ->first(),
            'primaryAsas' => ReportTemplate::query()
                ->where('type', AssessmentType::ASAS->value)
                ->where('is_active', true)
                ->first(),
        ];
    }
}
