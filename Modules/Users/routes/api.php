<?php

use Illuminate\Support\Facades\Route;
use Modules\Users\Http\Controllers\UsersController;

Route::middleware(['auth:sanctum', 'abilities:full-access'])->prefix('v1')->group(function () {
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UsersController::class)
            ->except(['store'])
            ->names('users');

        Route::post('users', [UsersController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('users.store');
    });
});

// Rétrocompatibilité : ancien chemin documenté, même action que POST /api/v1/users.
Route::prefix('auth')->middleware(['auth:sanctum', 'abilities:full-access', 'role:admin'])->group(function () {
    Route::post('managed-users', [UsersController::class, 'store'])
        ->middleware('throttle:5,1');
});
