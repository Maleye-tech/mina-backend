<?php

use Illuminate\Support\Facades\Route;
use Modules\Library\Http\Controllers\ContentController;
use Modules\Library\Http\Controllers\OfflineManifestController;
use Modules\Library\Http\Controllers\PodcastController;
use Modules\Library\Http\Controllers\TaxonomyController;

Route::middleware(['auth:sanctum', 'abilities:full-access'])->prefix('v1')->group(function () {
    Route::get('library/subjects', [TaxonomyController::class, 'subjects'])->name('library.subjects');
    Route::get('library/levels', [TaxonomyController::class, 'levels'])->name('library.levels');

    Route::get('library/contents', [ContentController::class, 'index'])->name('library.contents.index');
    Route::get('library/contents/{content}', [ContentController::class, 'show'])->name('library.contents.show');
    Route::post('library/contents/{content}/favorite', [ContentController::class, 'favorite'])->name('library.contents.favorite');
    Route::delete('library/contents/{content}/favorite', [ContentController::class, 'unfavorite'])->name('library.contents.unfavorite');

    Route::get('library/podcasts', [PodcastController::class, 'index'])->name('library.podcasts.index');
    Route::get('library/podcasts/{podcast}', [PodcastController::class, 'show'])->name('library.podcasts.show');
    Route::post('library/podcasts/{podcast}/favorite', [PodcastController::class, 'favorite'])->name('library.podcasts.favorite');
    Route::delete('library/podcasts/{podcast}/favorite', [PodcastController::class, 'unfavorite'])->name('library.podcasts.unfavorite');

    Route::get('library/offline-manifest', OfflineManifestController::class)->name('library.offline-manifest');

    Route::middleware('role:admin')->group(function () {
        Route::post('library/podcasts', [PodcastController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('library.podcasts.store');
    });
});
