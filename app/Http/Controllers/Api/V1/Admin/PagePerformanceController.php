<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PagePerformanceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:navigation,inertia'],
            'path' => ['required', 'string', 'max:500'],
            'duration_ms' => ['required', 'integer', 'min:0', 'max:300000'],
        ]);

        // Record all slow browser loads and a small baseline sample.
        if ($data['duration_ms'] >= 1000 || random_int(1, 10) === 1) {
            DB::table('page_performance_metrics')->insert([
                ...$data,
                'user_id' => $request->user()?->id,
                'occurred_at' => now(),
            ]);
        }

        return response()->json([], 202);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'system_admin', 'super_admin']), 403);

        $hours = min(168, max(1, $request->integer('hours', 24)));
        $metrics = DB::table('page_performance_metrics')->where('occurred_at', '>=', now()->subHours($hours))
            ->latest('occurred_at')->limit(20_000)->get();
        $rows = $metrics->groupBy(fn ($metric) => $metric->kind.'|'.$metric->path)->map(function ($group) {
            $durations = $group->pluck('duration_ms')->map(fn ($value) => (int) $value)->sort()->values();
            $p95Index = max(0, (int) ceil($durations->count() * .95) - 1);

            return [
                'kind' => $group->first()->kind,
                'path' => $group->first()->path,
                'samples' => $durations->count(),
                'average_ms' => (int) round($durations->average()),
                'p95_ms' => $durations[$p95Index],
                'maximum_ms' => $durations->max(),
                'slow_samples' => $durations->filter(fn ($duration) => $duration >= 1000)->count(),
            ];
        })->sortByDesc('p95_ms')->values()->take(100);

        return response()->json(['data' => $rows, 'meta' => ['hours' => $hours, 'samples' => $metrics->count()]]);
    }
}
