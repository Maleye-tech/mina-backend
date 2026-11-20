<?php

use Illuminate\Support\Facades\Route;
use Modules\Chat\Http\Controllers\ChatController;

/*
 * Le module Chat est exclusivement consommé en API par l'application
 * frontend ; seule une route racine de compatibilité est conservée.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/chats', [ChatController::class, 'index'])->name('chat.index');
});
