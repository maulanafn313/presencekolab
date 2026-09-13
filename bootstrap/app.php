<?php

use App\Http\Middleware\LegacyAuthMiddleware;
use App\Http\Middleware\ProtectSensitiveLegacyWrites;
use App\Http\Middleware\RequireAuthMiddleware;
use App\Http\Middleware\SafeResponses;
use App\Http\Middleware\ThrottleSensitiveRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(SafeResponses::class);
        $middleware->web(replace: [
            ValidateCsrfToken::class => ProtectSensitiveLegacyWrites::class,
        ]);
        $middleware->alias([
            'legacy.auth' => LegacyAuthMiddleware::class,
            'require.auth' => RequireAuthMiddleware::class,
        ]);
        $middleware->statefulApi();
        $middleware->web(append: [
            ThrottleSensitiveRequests::class,
        ]);
        $middleware->api(prepend: [ThrottleSensitiveRequests::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->has('ajax') || $request->has('action')) {
                $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
                if ($e instanceof ValidationException) {
                    return null;
                }

                return response()->json(['ok' => false, 'message' => $status >= 500 ? 'Terjadi kesalahan sistem.' : 'Permintaan tidak dapat diproses.'], $status);
            }
        });
    })->create();
