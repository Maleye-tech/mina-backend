<?php

use Illuminate\Support\Facades\Route;
use Modules\WellBeing\Http\Controllers\WellBeingController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('wellbeings', WellBeingController::class)->names('wellbeing');
});
