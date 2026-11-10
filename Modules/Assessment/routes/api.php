<?php

use Illuminate\Support\Facades\Route;
use Modules\Assessment\Http\Controllers\AssessmentController;

Route::middleware([
    'auth:sanctum',
    'abilities:full-access',
    'role:student,ambassador,professional,admin',
])->prefix('v1')->group(function () {
    Route::apiResource('assessments', AssessmentController::class)->names('assessment');
});
