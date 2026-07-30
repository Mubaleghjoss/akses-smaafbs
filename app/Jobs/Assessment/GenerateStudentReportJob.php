<?php

namespace App\Jobs\Assessment;

/**
 * Canonical queue job name used by the assessment workflow.
 *
 * The implementation remains in GenerateStudentReport so queued payloads keep
 * working if an earlier deployment already serialized that class name.
 */
class GenerateStudentReportJob extends GenerateStudentReport {}
