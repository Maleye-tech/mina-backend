<?php

use Illuminate\Support\Facades\Route;
use Modules\Payments\Http\Controllers\PaymentsController;

Route::prefix('v1/payments')->group(function (): void {
    // Webhook public endpoint (signature checked by gateway)
    Route::post('webhook/{provider}', [PaymentsController::class, 'webhook'])->name('payments.webhook');

    Route::middleware([
        'auth:sanctum',
        'abilities:full-access',
    ])->group(function (): void {
        Route::post('checkout', [PaymentsController::class, 'checkout'])->name('payments.checkout');
        Route::get('history', [PaymentsController::class, 'history'])->name('payments.history');
        Route::get('{payment}/receipt', [PaymentsController::class, 'receipt'])->name('payments.receipt');
        Route::post('{payment}/refund', [PaymentsController::class, 'refund'])->name('payments.refund');
    });
});
