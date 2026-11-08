<?php

use Illuminate\Support\Facades\Route;
use Modules\Progress\Http\Controllers\ProgressController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('progress', ProgressController::class)->names('progress');
});
