<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\AuthController;
use Modules\Auth\Http\Controllers\OnboardingController;
use Modules\Auth\Http\Controllers\OnboardingQuestionController;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::prefix('two-factor')->middleware([
        'auth:sanctum',
        'abilities:two-factor-setup',
        'role:admin',
        'throttle:6,1',
    ])->group(function () {
        Route::post('setup', [AuthController::class, 'setupTwoFactor']);
        Route::post('confirm-setup', [AuthController::class, 'confirmTwoFactorSetup']);
    });

    Route::prefix('two-factor')->middleware([
        'auth:sanctum',
        'abilities:two-factor-challenge',
        'role:admin',
        'throttle:6,1',
    ])->group(function () {
        Route::post('challenge', [AuthController::class, 'completeTwoFactorChallenge']);
    });

    Route::middleware(['auth:sanctum', 'abilities:full-access'])->group(function () {
        Route::patch('password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1');
        Route::delete('account', [AuthController::class, 'deleteAccount'])->middleware('throttle:5,1');
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::get('me', [AuthController::class, 'me']);
        Route::patch('settings', [AuthController::class, 'updateSettings'])->middleware('throttle:10,1');
        Route::post('email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
            ->middleware('throttle:6,1');

        Route::middleware('role:student')->group(function () {
            Route::get('onboarding', [OnboardingController::class, 'show']);
            Route::post('onboarding', [AuthController::class, 'completeStudentOnboarding'])
                ->middleware('consent:personal_data');
            Route::patch('student-profile', [AuthController::class, 'updateStudentProfile'])
                ->middleware('consent:personal_data');
        });

        Route::middleware('role:admin')->group(function () {
            Route::get('onboarding/questions', [OnboardingQuestionController::class, 'index']);
            Route::patch('onboarding/questions/{key}', [OnboardingQuestionController::class, 'update']);
        });
    });
});
