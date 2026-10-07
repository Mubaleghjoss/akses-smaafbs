<?php

namespace Tests\Unit;

use App\Support\Assessment\AssessmentNumberFormatter;
use PHPUnit\Framework\TestCase;

class AssessmentNumberFormatterTest extends TestCase
{
    public function test_it_limits_display_to_two_decimals_and_removes_trailing_zeroes(): void
    {
        $this->assertSame('99.99', AssessmentNumberFormatter::score('99.9900'));
        $this->assertSame('85.5', AssessmentNumberFormatter::score('85.5000'));
        $this->assertSame('100', AssessmentNumberFormatter::score('100.0000'));
        $this->assertSame('0', AssessmentNumberFormatter::score(0));
        $this->assertSame('-', AssessmentNumberFormatter::score(null));
        $this->assertSame('-', AssessmentNumberFormatter::score(''));
    }

    public function test_it_rounds_numeric_rapor_scores_to_whole_numbers(): void
    {
        $this->assertSame('94', AssessmentNumberFormatter::scoreRapor('93.50'));
        $this->assertSame('93', AssessmentNumberFormatter::scoreRapor('93.49'));
        $this->assertSame('90', AssessmentNumberFormatter::scoreRapor('90.33'));
    }
}
