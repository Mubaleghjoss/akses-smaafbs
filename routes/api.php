<?php

use App\Http\Controllers\Api\LiteracySchoolNetworkMonitorController;
use App\Http\Controllers\Api\PublicConnectivityController;
use App\Http\Controllers\Api\StudentSyncApplyController;
use App\Http\Controllers\Api\StudentSyncPreviewController;
use App\Http\Controllers\Api\TagihanStudentIntegrationController;
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

Route::middleware('throttle:public_connectivity')->post(
    '/v1/monitoring/public-connectivity',
    PublicConnectivityController::class,
)->name('api.monitoring.public-connectivity');

Route::prefix('/internal/student-sync')->middleware([
    'throttle:student_sync_receiver',
    'student.sync.signature',
])->group(function (): void {
    Route::post('/preview', StudentSyncPreviewController::class)
        ->name('api.internal.student-sync.preview');

    Route::post('/apply', StudentSyncApplyController::class)
        ->name('api.internal.student-sync.apply');
});
