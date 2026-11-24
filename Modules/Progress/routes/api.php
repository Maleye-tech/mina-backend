<?php

use Illuminate\Support\Facades\Route;
use Modules\Progress\Http\Controllers\ProgressController;

Route::middleware([
    'auth:sanctum',
    'abilities:full-access',
    'role:student,ambassador,professional,admin',
])->prefix('v1')->group(function (): void {
    Route::get('progress', [ProgressController::class, 'index'])->name('progress.index');
    Route::get('progress/stats', [ProgressController::class, 'stats'])->name('progress.stats');
    Route::get('progress/history', [ProgressController::class, 'history'])->name('progress.history');
    Route::get('progress/weak-points', [ProgressController::class, 'weakPoints'])->name('progress.weak-points');
});
