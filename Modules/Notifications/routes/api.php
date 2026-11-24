<?php

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Http\Controllers\NotificationsController;

Route::prefix('v1/notifications')->middleware([
    'auth:sanctum',
    'abilities:full-access',
])->group(function (): void {
    Route::get('/', [NotificationsController::class, 'index'])->name('notifications.index');
    Route::post('read-all', [NotificationsController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('{id}/read', [NotificationsController::class, 'markAsRead'])->name('notifications.read');
    Route::put('preferences', [NotificationsController::class, 'updatePreferences'])->name('notifications.preferences');
});
