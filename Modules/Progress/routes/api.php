<?php

use Illuminate\Support\Facades\Route;
use Modules\Progress\Http\Controllers\ProgressController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('progress', ProgressController::class)->names('progress');
});
