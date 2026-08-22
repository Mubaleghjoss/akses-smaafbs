<?php

namespace App\Jobs\Assessment;

/**
 * Canonical queue job name used by the assessment workflow.
 *
 * The parent class is retained as a backwards-compatible queue payload name.
 */
class GenerateClassReportsJob extends GenerateClassReports {}
