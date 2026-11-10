<?php

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Http\Controllers\DashboardController;

Route::middleware(['auth:sanctum', 'abilities:full-access'])->prefix('v1')->group(function () {
    Route::apiResource('dashboards', DashboardController::class)->names('dashboard');
});
