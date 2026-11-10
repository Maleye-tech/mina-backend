<?php

use Illuminate\Support\Facades\Route;
use Modules\Appointments\Http\Controllers\AppointmentsController;

Route::middleware(['auth:sanctum', 'abilities:full-access'])->prefix('v1')->group(function () {
    Route::apiResource('appointments', AppointmentsController::class)->names('appointments');
});
