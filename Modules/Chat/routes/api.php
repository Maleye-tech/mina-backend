<?php

use Illuminate\Support\Facades\Route;
use Modules\Chat\Http\Controllers\ConversationController;
use Modules\Chat\Http\Controllers\MessageController;

/*
 * API Conversations (module Chat).
 *
 * Toutes les routes exigent un utilisateur authentifié, le jeton
 * « full-access » et un rôle Care (étudiant ou professionnel de santé
 * mentale). Chaque utilisateur n'accède qu'à ses propres conversations.
 */
Route::middleware([
    'auth:sanctum',
    'abilities:full-access',
    'role:student,professional',
])->prefix('v1')->name('chat.')->group(function (): void {
    Route::prefix('conversations')->name('conversations.')->group(function (): void {
        /*
         * Liste et ouverture des conversations de l'utilisateur.
         */
        Route::get('/', [ConversationController::class, 'index'])->name('index');
        Route::post('/', [ConversationController::class, 'store'])->name('store');
        Route::get('/{conversation}', [ConversationController::class, 'show'])->name('show');

        /*
         * Messages d'une conversation : consultation (marque les messages
         * reçus comme lus) et envoi.
         */
        Route::prefix('{conversation}/messages')->name('messages.')->group(function (): void {
            Route::get('/', [MessageController::class, 'index'])->name('index');
            Route::post('/', [MessageController::class, 'store'])
                ->middleware('throttle:chat-messages')
                ->name('store');
        });
    });
});
