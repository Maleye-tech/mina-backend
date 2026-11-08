<?php

use Illuminate\Support\Facades\Route;
use Modules\WellBeing\Http\Controllers\WellBeingController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('wellbeings', WellBeingController::class)->names('wellbeing');
});
