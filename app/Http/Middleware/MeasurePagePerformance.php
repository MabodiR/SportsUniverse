<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class MeasurePagePerformance
{
    private float $startedAt = 0;

    private ?int $durationMs = null;

    public function handle(Request $request, Closure $next): Response
    {
        $this->startedAt = microtime(true);
        $response = $next($request);
        $this->durationMs = (int) round((microtime(true) - $this->startedAt) * 1000);
        $response->headers->set('Server-Timing', 'app;dur='.$this->durationMs);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->method() !== 'GET' || $request->is('up', 'media/*', 'watch/*/stream') || $this->durationMs === null) {
            return;
        }

        // Keep every slow request and a 10% baseline sample of healthy requests.
        if ($this->durationMs < 1000 && random_int(1, 10) !== 1) {
            return;
        }

        DB::table('page_performance_metrics')->insert([
            'user_id' => $request->user()?->id,
            'kind' => $request->is('api/*') ? 'api' : 'server',
            'path' => '/'.ltrim($request->path(), '/'),
            'duration_ms' => $this->durationMs,
            'status_code' => $response->getStatusCode(),
            'occurred_at' => now(),
        ]);
    }
}
