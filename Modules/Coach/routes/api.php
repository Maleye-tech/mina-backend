<?php

use Illuminate\Support\Facades\Route;
use Modules\Coach\Http\Controllers\CoachController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('coaches', CoachController::class)->names('coach');
});
