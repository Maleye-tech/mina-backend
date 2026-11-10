<?php

use Illuminate\Support\Facades\Route;
use Modules\Chat\Http\Controllers\ChatController;

Route::middleware(['auth:sanctum', 'abilities:full-access'])->prefix('v1')->group(function () {
    Route::apiResource('chats', ChatController::class)->names('chat');
});
