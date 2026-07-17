<?php

use App\Http\Controllers\Api\TagihanStudentIntegrationController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'throttle:tagihan_student_api',
    'tagihan.student.integration',
])->get(
    '/v1/integrations/tagihan/students',
    TagihanStudentIntegrationController::class,
)->name('api.integrations.tagihan.students');
