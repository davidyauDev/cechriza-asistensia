<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Configurar redirección para huéspedes no autenticados
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return route('login');
        });

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->expectsJson()) {
                $status = method_exists($e, 'getStatusCode')
                    ? $e->getStatusCode()
                    : 500;

                $mode = env('MODE', 'production');

               

                if ($mode === 'environment') {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage(),
                        'error' => [
                            'trace' => $e->getTraceAsString(),
                        ],
                    ], $status);
                }

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], $status);
            }
        });

    })->create();
