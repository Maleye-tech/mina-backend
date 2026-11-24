<?php

use Illuminate\Support\Facades\Route;
use Modules\Subscriptions\Http\Controllers\SubscriptionsController;

Route::prefix('v1/subscriptions')->group(function (): void {
    Route::get('plans', [SubscriptionsController::class, 'plans'])->name('subscriptions.plans');

    Route::middleware([
        'auth:sanctum',
        'abilities:full-access',
    ])->group(function (): void {
        Route::get('current', [SubscriptionsController::class, 'current'])->name('subscriptions.current');
        Route::post('trial', [SubscriptionsController::class, 'startTrial'])->name('subscriptions.trial');
        Route::post('redeem-code', [SubscriptionsController::class, 'redeemCode'])->name('subscriptions.redeem-code');
        Route::post('cancel', [SubscriptionsController::class, 'cancel'])->name('subscriptions.cancel');
    });
});
