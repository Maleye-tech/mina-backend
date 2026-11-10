<?php

namespace Modules\Assessment\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Assessment';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     */
    public function boot(): void
    {
        RateLimiter::for('qcm-submissions', fn (Request $request): Limit => $this->perMinuteForUser(
            $request,
            60,
            'Trop de réponses envoyées. Patientez un instant avant de réessayer.',
        ));

        RateLimiter::for('flashcard-reviews', fn (Request $request): Limit => $this->perMinuteForUser(
            $request,
            60,
            'Trop de révisions envoyées. Patientez un instant avant de réessayer.',
        ));

        RateLimiter::for('clinical-case-answers', fn (Request $request): Limit => $this->perMinuteForUser(
            $request,
            60,
            'Trop de réponses envoyées. Patientez un instant avant de réessayer.',
        ));

        RateLimiter::for('qcm-imports', fn (Request $request): Limit => $this->perMinuteForUser(
            $request,
            10,
            'Trop d’imports lancés. Patientez une minute avant de réessayer.',
        ));

        parent::boot();
    }

    /**
     * Limite par minute et par utilisateur (ou adresse IP en invité),
     * avec une réponse 429 en français.
     */
    private function perMinuteForUser(Request $request, int $attempts, string $message): Limit
    {
        return Limit::perMinute($attempts)
            ->by($request->user()?->id ?: $request->ip())
            ->response(fn (Request $request, array $headers): JsonResponse => response()->json([
                'message' => $message,
            ], 429, $headers));
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')->group(module_path($this->name, '/routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     */
    protected function mapApiRoutes(): void
    {
        Route::middleware('api')->prefix('api')->name('api.')->group(module_path($this->name, '/routes/api.php'));
    }
}
