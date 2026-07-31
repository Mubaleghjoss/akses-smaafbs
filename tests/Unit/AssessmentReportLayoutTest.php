<?php

namespace Tests\Unit;

use App\Support\Assessment\Reporting\AssessmentReportLayout;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssessmentReportLayoutTest extends TestCase
{
    public function test_three_page_layout_is_normalized_and_keeps_required_sections(): void
    {
        $settings = app(AssessmentReportLayout::class)->validateAndNormalize([
            'layout' => [
                'sections' => AssessmentReportLayout::threePageDefaults(),
            ],
        ]);
        $pages = app(AssessmentReportLayout::class)->pages($settings);

        $this->assertSame(AssessmentReportLayout::VERSION, data_get($settings, 'layout.version'));
        $this->assertSame([1, 2, 3], array_keys($pages));
        $this->assertSame('identity', $pages[1][0]['type']);
        $this->assertSame('subject_competencies', $pages[2][1]['type']);
        $this->assertSame('signatures', collect($pages[3])->last()['type']);
        $this->assertTrue(app(AssessmentReportLayout::class)->requiresAttitudes($settings));
    }

    public function test_layout_rejects_unknown_sections_pages_outside_three_and_missing_required_sections(): void
    {
        foreach ([
            [['type' => 'unsafe_html', 'page' => 1]],
            [['type' => 'identity', 'page' => 4]],
            [
                ['type' => 'identity', 'page' => 1],
                ['type' => 'subject_summary', 'page' => 1],
            ],
        ] as $sections) {
            try {
                app(AssessmentReportLayout::class)->validateAndNormalize([
                    'layout' => ['sections' => $sections],
                ]);
                $this->fail('Layout rapor yang tidak aman seharusnya ditolak.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }
}
