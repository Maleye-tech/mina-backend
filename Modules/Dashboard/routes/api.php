<?php

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Http\Controllers\DashboardController;

Route::middleware([
    'auth:sanctum',
    'abilities:full-access',
    'role:student,ambassador,professional,admin',
])->prefix('v1')->group(function () {
    Route::get('dashboards', [DashboardController::class, 'index'])->name('dashboards.index');
});
