<?php

use Illuminate\Support\Facades\Route;
use Modules\Coach\Http\Controllers\CoachController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('coaches', CoachController::class)->names('coach');
});
