<?php

use Illuminate\Support\Facades\Route;

/*
 * Le module Mina Care est consommé par l'application frontend via l'API.
 * La page web historique est conservée sous forme de redirection afin de ne
 * pas casser les liens existants.
 */
Route::get('wellbeings', function () {
    $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

    return redirect()->away($frontendUrl !== '' ? $frontendUrl.'/wellbeing' : '/');
})->middleware(['auth', 'verified'])->name('wellbeing.index');
