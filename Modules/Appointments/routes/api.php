<?php

use Illuminate\Support\Facades\Route;
use Modules\Appointments\Http\Controllers\AlertController;
use Modules\Appointments\Http\Controllers\AppointmentsController;
use Modules\Appointments\Http\Controllers\ConsultationRequestController;
use Modules\Appointments\Http\Controllers\PrivateNoteController;
use Modules\Appointments\Http\Controllers\ProfessionalAlertController;
use Modules\Appointments\Http\Controllers\ProfessionalAppointmentController;
use Modules\Appointments\Http\Controllers\ProfessionalAvailabilityController;
use Modules\Appointments\Http\Controllers\ProfessionalProfileController;
use Modules\Appointments\Http\Controllers\StudentCareController;

Route::prefix('v1/appointments')->middleware([
    'auth:sanctum',
    'abilities:full-access',
    'throttle:60,1',
])->group(function (): void {
    Route::get('professionals', [AppointmentsController::class, 'professionals'])->name('appointments.professionals');
    Route::get('professionals/{id}/slots', [AppointmentsController::class, 'slots'])->name('appointments.professionals.slots');
    Route::post('/', [AppointmentsController::class, 'store'])
        ->middleware('role:student')
        ->name('appointments.store');

    Route::middleware(['role:professional', 'professional.verified'])->group(function (): void {
        Route::get('availability', [AppointmentsController::class, 'availability'])->name('appointments.availability.index');
        Route::post('availability', [AppointmentsController::class, 'storeAvailability'])->name('appointments.availability.store');
        Route::patch('availability/{id}', [AppointmentsController::class, 'updateAvailability'])->name('appointments.availability.update');
        Route::delete('availability/{id}', [AppointmentsController::class, 'destroyAvailability'])->name('appointments.availability.destroy');

        Route::get('consultation-requests', [ConsultationRequestController::class, 'index'])->name('appointments.consultation-requests.index');
        Route::post('consultation-requests/{id}/accept', [ConsultationRequestController::class, 'accept'])->name('appointments.consultation-requests.accept');
        Route::post('consultation-requests/{id}/decline', [ConsultationRequestController::class, 'decline'])->name('appointments.consultation-requests.decline');
    });

    Route::middleware('professional.verified:optional')->group(function (): void {
        Route::get('/', [AppointmentsController::class, 'index'])->name('appointments.index');

        Route::get('private-notes', [PrivateNoteController::class, 'index'])->name('appointments.private-notes.index');
        Route::post('private-notes', [PrivateNoteController::class, 'store'])->name('appointments.private-notes.store');
        Route::patch('private-notes/{id}', [PrivateNoteController::class, 'update'])->name('appointments.private-notes.update');
        Route::delete('private-notes/{id}', [PrivateNoteController::class, 'destroy'])->name('appointments.private-notes.destroy');

        Route::get('alerts', [AlertController::class, 'index'])->name('appointments.alerts.index');
        Route::patch('alerts/{id}', [AlertController::class, 'update'])->name('appointments.alerts.update');

        Route::get('{appointment}', [AppointmentsController::class, 'show'])->name('appointments.show');
        Route::post('{appointment}/cancel', [AppointmentsController::class, 'cancel'])->name('appointments.cancel');
        Route::post('{appointment}/reschedule', [AppointmentsController::class, 'reschedule'])->name('appointments.reschedule');
    });
});

$registerProfessionalRoutes = function (string $prefix = ''): void {
    $pathPrefix = $prefix === '' ? '' : $prefix.'/';
    $namePrefix = $prefix === '' ? '' : $prefix.'.';

    Route::prefix($pathPrefix.'pro')->middleware([
        'auth:sanctum',
        'abilities:full-access',
        'role:professional',
        'throttle:60,1',
    ])->group(function () use ($namePrefix): void {
        Route::get('profile', [ProfessionalProfileController::class, 'show'])->name("{$namePrefix}pro.profile.show");
        Route::put('profile', [ProfessionalProfileController::class, 'update'])->name("{$namePrefix}pro.profile.update");
        Route::post('profile/accept-charter', [ProfessionalProfileController::class, 'acceptCharter'])->name("{$namePrefix}pro.profile.accept-charter");

        Route::middleware('professional.verified')->group(function () use ($namePrefix): void {
            Route::get('availability', [ProfessionalAvailabilityController::class, 'index'])->name("{$namePrefix}pro.availability.index");
            Route::post('availability', [ProfessionalAvailabilityController::class, 'store'])->name("{$namePrefix}pro.availability.store");
            Route::put('availability/{id}', [ProfessionalAvailabilityController::class, 'update'])->name("{$namePrefix}pro.availability.update");
            Route::delete('availability/{id}', [ProfessionalAvailabilityController::class, 'destroy'])->name("{$namePrefix}pro.availability.destroy");

            Route::get('appointments', [ProfessionalAppointmentController::class, 'index'])->name("{$namePrefix}pro.appointments.index");
            Route::get('appointments/{id}', [ProfessionalAppointmentController::class, 'show'])->name("{$namePrefix}pro.appointments.show");
            Route::post('appointments/{id}/confirm', [ProfessionalAppointmentController::class, 'confirm'])->name("{$namePrefix}pro.appointments.confirm");
            Route::post('appointments/{id}/decline', [ProfessionalAppointmentController::class, 'decline'])->name("{$namePrefix}pro.appointments.decline");
            Route::post('appointments/{id}/propose', [ProfessionalAppointmentController::class, 'propose'])->name("{$namePrefix}pro.appointments.propose");
            Route::post('appointments/{id}/complete', [ProfessionalAppointmentController::class, 'complete'])->name("{$namePrefix}pro.appointments.complete");
            Route::put('appointments/{id}', [ProfessionalAppointmentController::class, 'updateMeetingUrl'])->name("{$namePrefix}pro.appointments.meeting-url");

            Route::get('appointments/{id}/messages', [ProfessionalAppointmentController::class, 'messages'])->name("{$namePrefix}pro.appointments.messages.index");
            Route::post('appointments/{id}/messages', [ProfessionalAppointmentController::class, 'sendMessage'])
                ->middleware('throttle:30,1')
                ->name("{$namePrefix}pro.appointments.messages.store");

            Route::get('appointments/{id}/note', [ProfessionalAppointmentController::class, 'getNote'])->name("{$namePrefix}pro.appointments.note.show");
            Route::put('appointments/{id}/note', [ProfessionalAppointmentController::class, 'saveNote'])->name("{$namePrefix}pro.appointments.note.save");

            Route::get('alerts', [ProfessionalAlertController::class, 'index'])->name("{$namePrefix}pro.alerts.index");
            Route::post('alerts/{id}/handle', [ProfessionalAlertController::class, 'handle'])->name("{$namePrefix}pro.alerts.handle");
        });
    });
};

$registerProfessionalRoutes();
$registerProfessionalRoutes('v1');

Route::middleware([
    'auth:sanctum',
    'abilities:full-access',
    'throttle:60,1',
])->group(function (): void {
    Route::prefix('professionals')->group(function (): void {
        Route::get('/', [StudentCareController::class, 'professionals'])->name('care.professionals.index');
        Route::get('{id}/slots', [StudentCareController::class, 'slots'])->name('care.professionals.slots');
    });

    Route::prefix('appointments')->group(function (): void {
        Route::get('/', [StudentCareController::class, 'appointments'])->name('care.appointments.index');
        Route::get('{id}', [StudentCareController::class, 'show'])->name('care.appointments.show');
        Route::post('/', [StudentCareController::class, 'book'])->middleware('role:student')->name('care.appointments.book');
        Route::post('{id}/cancel', [StudentCareController::class, 'cancel'])->name('care.appointments.cancel');
        Route::post('{id}/accept-proposal', [StudentCareController::class, 'acceptProposal'])->name('care.appointments.accept-proposal');

        Route::get('{id}/messages', [StudentCareController::class, 'messages'])->name('care.appointments.messages.index');
        Route::post('{id}/messages', [StudentCareController::class, 'sendMessage'])
            ->middleware('throttle:30,1')
            ->name('care.appointments.messages.store');
    });

    Route::post('alerts', [StudentCareController::class, 'alert'])
        ->middleware(['role:student', 'throttle:5,1'])
        ->name('care.alerts.store');
});
