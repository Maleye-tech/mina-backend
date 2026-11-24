<?php

use Illuminate\Support\Facades\Route;
use Modules\Appointments\Http\Controllers\AppointmentsController;

Route::prefix('v1/appointments')->middleware([
    'auth:sanctum',
    'abilities:full-access',
])->group(function (): void {
    Route::get('professionals', [AppointmentsController::class, 'professionals'])->name('appointments.professionals');
    Route::get('professionals/{id}/slots', [AppointmentsController::class, 'slots'])->name('appointments.slots');
    Route::get('/', [AppointmentsController::class, 'index'])->name('appointments.index');
    Route::post('/', [AppointmentsController::class, 'store'])->name('appointments.store');
    Route::get('{appointment}', [AppointmentsController::class, 'show'])->name('appointments.show');
    Route::post('{appointment}/cancel', [AppointmentsController::class, 'cancel'])->name('appointments.cancel');
    Route::post('{appointment}/reschedule', [AppointmentsController::class, 'reschedule'])->name('appointments.reschedule');
});
