<?php

use Illuminate\Support\Facades\Route;
use Modules\Events\Http\Controllers\EventsController;

Route::prefix('v1/events')->group(function (): void {
    Route::get('/', [EventsController::class, 'index'])->name('events.index');
    Route::get('{event}', [EventsController::class, 'show'])->name('events.show');

    Route::middleware([
        'auth:sanctum',
        'abilities:full-access',
    ])->group(function (): void {
        Route::get('my/registrations', [EventsController::class, 'myRegistrations'])->name('events.my-registrations');
        Route::post('{event}/register', [EventsController::class, 'register'])->name('events.register');
        Route::delete('{event}/register', [EventsController::class, 'unregister'])->name('events.unregister');
    });
});
