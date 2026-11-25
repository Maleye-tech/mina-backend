<?php

use Illuminate\Support\Facades\Route;
use Modules\Appointments\Http\Controllers\AlertController;
use Modules\Appointments\Http\Controllers\AppointmentsController;
use Modules\Appointments\Http\Controllers\ConsultationRequestController;
use Modules\Appointments\Http\Controllers\PrivateNoteController;

Route::prefix('v1/appointments')->middleware([
    'auth:sanctum',
    'abilities:full-access',
])->group(function (): void {
    Route::get('professionals', [AppointmentsController::class, 'professionals'])->name('appointments.professionals');
    Route::get('professionals/{id}/slots', [AppointmentsController::class, 'slots'])->name('appointments.slots');
    Route::get('/', [AppointmentsController::class, 'index'])->name('appointments.index');
    Route::post('/', [AppointmentsController::class, 'store'])->name('appointments.store');

    /*
     * Disponibilités du professionnel connecté (CRUD).
     * Déclaré avant les routes paramétrées « {appointment} » pour éviter que
     * le segment « availability » soit capturé comme identifiant de rendez-vous.
     */
    Route::get('availability', [AppointmentsController::class, 'availability'])->name('appointments.availability');
    Route::post('availability', [AppointmentsController::class, 'storeAvailability'])->name('appointments.availability.store');
    Route::patch('availability/{id}', [AppointmentsController::class, 'updateAvailability'])->name('appointments.availability.update');
    Route::delete('availability/{id}', [AppointmentsController::class, 'destroyAvailability'])->name('appointments.availability.destroy');

    /*
     * Demandes de consultation (file du professionnel connecté).
     */
    Route::get('consultation-requests', [ConsultationRequestController::class, 'index'])->name('consultation-requests.index');
    Route::post('consultation-requests/{id}/accept', [ConsultationRequestController::class, 'accept'])->name('consultation-requests.accept');
    Route::post('consultation-requests/{id}/decline', [ConsultationRequestController::class, 'decline'])->name('consultation-requests.decline');

    /*
     * Notes privées du professionnel connecté.
     */
    Route::get('private-notes', [PrivateNoteController::class, 'index'])->name('private-notes.index');
    Route::post('private-notes', [PrivateNoteController::class, 'store'])->name('private-notes.store');
    Route::patch('private-notes/{id}', [PrivateNoteController::class, 'update'])->name('private-notes.update');
    Route::delete('private-notes/{id}', [PrivateNoteController::class, 'destroy'])->name('private-notes.destroy');

    /*
     * Alertes du professionnel connecté.
     */
    Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::patch('alerts/{id}', [AlertController::class, 'update'])->name('alerts.update');

    Route::get('{appointment}', [AppointmentsController::class, 'show'])->name('appointments.show');
    Route::post('{appointment}/cancel', [AppointmentsController::class, 'cancel'])->name('appointments.cancel');
    Route::post('{appointment}/reschedule', [AppointmentsController::class, 'reschedule'])->name('appointments.reschedule');
});
