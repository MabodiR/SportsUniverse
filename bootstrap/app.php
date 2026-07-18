<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\MediaPermissionHeaders;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['auth:sanctum']])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [HandleInertiaRequests::class, MediaPermissionHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        $exceptions->respond(function (SymfonyResponse $response) {
            $request = request();
            $status = $response->getStatusCode();
            if ($request->is('api/*') || $request->expectsJson()) {
                return $response;
            }
            if ($status === 419) {
                return redirect('/session-expired');
            }
            if ($request->header('X-Inertia') && in_array($status, [401, 403, 404, 429, 500, 503], true)) {
                return Inertia::render('Errors/Show', ['status' => $status])->toResponse($request)->setStatusCode($status);
            }

            return $response;
        });
    })->create();
