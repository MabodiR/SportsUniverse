<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MediaPermissionHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(self), display-capture=(self)');

        return $response;
    }
}
