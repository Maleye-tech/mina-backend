<?php

use Illuminate\Support\Facades\Route;
use Modules\Assessment\Http\Controllers\ClinicalCaseAttemptController;
use Modules\Assessment\Http\Controllers\ClinicalCaseController;
use Modules\Assessment\Http\Controllers\FlashcardDeckController;
use Modules\Assessment\Http\Controllers\FlashcardReviewController;
use Modules\Assessment\Http\Controllers\QcmImportController;
use Modules\Assessment\Http\Controllers\QuestionController;
use Modules\Assessment\Http\Controllers\SessionController;

Route::middleware([
    'auth:sanctum',
    'abilities:full-access',
    'role:student,ambassador,professional,admin',
])->prefix('v1')->name('assessment.')->group(function (): void {
    /*
     * Catalogue des questions : lecture seule, questions publiées uniquement.
     */
    Route::prefix('qcm/questions')->name('questions.')->group(function (): void {
        Route::get('/', [QuestionController::class, 'index'])->name('index');
        Route::get('/{question}', [QuestionController::class, 'show'])->name('show');
    });

    /*
     * Sessions d'entraînement : démarrage, consultation, réponse, clôture.
     */
    Route::prefix('qcm/sessions')->name('sessions.')->group(function (): void {
        Route::get('/', [SessionController::class, 'index'])->name('index');
        Route::post('/', [SessionController::class, 'start'])->name('store');
        Route::get('/{attempt}', [SessionController::class, 'show'])->name('show');
        Route::post('/{attempt}/answers', [SessionController::class, 'answer'])
            ->middleware('throttle:qcm-submissions')
            ->name('answers');
        Route::post('/{attempt}/finish', [SessionController::class, 'finish'])
            ->middleware('throttle:qcm-submissions')
            ->name('finish');
    });

    /*
     * Import de questions QCM depuis un fichier CSV : réservé aux
     * administrateurs, traité en arrière-plan.
     */
    Route::prefix('qcm/imports')->name('imports.')->middleware('role:admin')->group(function (): void {
        Route::get('/', [QcmImportController::class, 'index'])->name('index');
        Route::post('/', [QcmImportController::class, 'store'])
            ->middleware('throttle:qcm-imports')
            ->name('store');
        Route::get('/{import}', [QcmImportController::class, 'show'])->name('show');
    });

    /*
     * Cartes mémoire à répétition espacée (algorithme SM-2) : paquets,
     * file de révision du jour et notation.
     */
    Route::prefix('flashcards')->name('flashcards.')->group(function (): void {
        Route::prefix('decks')->name('decks.')->group(function (): void {
            Route::get('/', [FlashcardDeckController::class, 'index'])->name('index');
            Route::get('/{deck}', [FlashcardDeckController::class, 'show'])->name('show');
        });

        Route::get('/reviews/today', [FlashcardReviewController::class, 'today'])->name('reviews.today');
        Route::post('/{flashcard}/reviews', [FlashcardReviewController::class, 'store'])
            ->middleware('throttle:flashcard-reviews')
            ->name('reviews.store');
    });

    /*
     * Cas cliniques adaptés à l'Afrique de l'Ouest (contexte local fictif) :
     * catalogue, puis tentatives en étapes avec corrigés révélés
     * progressivement.
     */
    Route::prefix('clinical-cases')->name('cases.')->group(function (): void {
        Route::get('/', [ClinicalCaseController::class, 'index'])->name('index');

        /*
         * Tentatives de cas cliniques : historique, démarrage, consultation,
         * réponse étape par étape, clôture.
         *
         * Le groupe statique « attempts » est déclaré avant la route
         * paramétrée « {clinicalCase} », contrainte aux identifiants
         * numériques, pour éviter toute collision.
         */
        Route::prefix('attempts')->name('attempts.')->group(function (): void {
            Route::get('/', [ClinicalCaseAttemptController::class, 'index'])->name('index');
            Route::post('/', [ClinicalCaseAttemptController::class, 'start'])->name('store');
            Route::get('/{attempt}', [ClinicalCaseAttemptController::class, 'show'])
                ->whereNumber('attempt')
                ->name('show');
            Route::post('/{attempt}/answers', [ClinicalCaseAttemptController::class, 'answer'])
                ->middleware('throttle:clinical-case-answers')
                ->name('answers');
            Route::post('/{attempt}/finish', [ClinicalCaseAttemptController::class, 'finish'])
                ->middleware('throttle:clinical-case-answers')
                ->name('finish');
        });

        Route::get('/{clinicalCase}', [ClinicalCaseController::class, 'show'])
            ->whereNumber('clinicalCase')
            ->name('show');
    });
});
