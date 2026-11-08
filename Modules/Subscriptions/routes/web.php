<?php

use Illuminate\Support\Facades\Route;
use Modules\Subscriptions\Http\Controllers\SubscriptionsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('subscriptions', SubscriptionsController::class)->names('subscriptions');
});
