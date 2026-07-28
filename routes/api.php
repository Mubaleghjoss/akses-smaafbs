<?php

use App\Http\Controllers\Api\TagihanStudentIntegrationController;
use App\Http\Controllers\Api\LiteracySchoolNetworkMonitorController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'throttle:tagihan_student_api',
    'tagihan.student.integration',
])->get(
    '/v1/integrations/tagihan/students',
    TagihanStudentIntegrationController::class,
)->name('api.integrations.tagihan.students');

Route::middleware([
    'throttle:literacy_school_monitor',
    'literacy.school.monitor',
])->post(
    '/v1/monitoring/school-network',
    LiteracySchoolNetworkMonitorController::class,
)->name('api.monitoring.school-network');
