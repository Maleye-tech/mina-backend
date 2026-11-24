<?php

use App\Http\Controllers\PrivacyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:sanctum', 'abilities:full-access']);

Route::get('/v1/health', function () {
    try {
        DB::connection()->getPdo();
        $dbStatus = 'connected';
    } catch (\Throwable $e) {
        $dbStatus = 'disconnected';
    }

    return response()->json([
        'status' => 'healthy',
        'database' => $dbStatus,
        'app' => config('app.name'),
        'environment' => config('app.env'),
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::middleware([
    'auth:sanctum',
    'abilities:full-access',
])->prefix('v1/privacy')->group(function (): void {
    Route::get('export', [PrivacyController::class, 'export'])->name('privacy.export');
    Route::delete('delete-account', [PrivacyController::class, 'deleteAccount'])->name('privacy.delete-account');
});
