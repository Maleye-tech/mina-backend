<?php

use Illuminate\Support\Facades\Route;
use Modules\WellBeing\Http\Controllers\AssessmentTestController;
use Modules\WellBeing\Http\Controllers\EmergencyContactController;
use Modules\WellBeing\Http\Controllers\UserTestResultController;
use Modules\WellBeing\Http\Controllers\WellbeingArticleController;

/*
 * API Mina Care (module WellBeing).
 *
 * Toutes les routes exigent un utilisateur authentifié, le jeton
 * « full-access » et le consentement courant au traitement des données de
 * santé mentale. Le module est consommé par l'application frontend.
 */
Route::middleware([
    'auth:sanctum',
    'abilities:full-access',
    'role:student,ambassador,professional,admin',
    'consent:mental_health_data',
])->prefix('v1')->group(function (): void {
    /*
     * Route de compatibilité historique : renvoie le catalogue des fiches.
     */
    Route::get('wellbeings', [WellbeingArticleController::class, 'index'])
        ->name('wellbeing.index');

    Route::prefix('wellbeing')->name('wellbeing.')->group(function (): void {
        /*
         * Fiches de prévention publiées.
         */
        Route::prefix('articles')->name('articles.')->group(function (): void {
            Route::get('/', [WellbeingArticleController::class, 'index'])->name('index');
            Route::get('/{article:slug}', [WellbeingArticleController::class, 'show'])->name('show');
        });

        /*
         * Tests d'auto-évaluation : catalogue, détail et soumission.
         */
        Route::prefix('tests')->name('tests.')->group(function (): void {
            Route::get('/', [AssessmentTestController::class, 'index'])->name('index');
            Route::get('/{test}', [AssessmentTestController::class, 'show'])->name('show');
            Route::post('/{test}/results', [AssessmentTestController::class, 'submit'])
                ->middleware('throttle:wellbeing-test-submissions')
                ->name('results.store');
        });

        /*
         * Historique personnel des résultats.
         */
        Route::prefix('results')->name('results.')->group(function (): void {
            Route::get('/', [UserTestResultController::class, 'index'])->name('index');
            Route::get('/{result}', [UserTestResultController::class, 'show'])->name('show');
        });

        /*
         * Contacts d'urgence actifs.
         */
        Route::get('emergency-contacts', [EmergencyContactController::class, 'index'])
            ->name('emergency-contacts.index');
    });
});
