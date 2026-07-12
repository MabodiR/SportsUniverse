<?php

namespace App\Http\Controllers\Api\V1\Discovery;

use App\Domain\Discovery\Contracts\ProfileSearchEngine;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Discovery\SearchProfilesRequest;
use App\Http\Resources\Api\V1\Profiles\ProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProfileSearchController extends Controller
{
    public function __invoke(SearchProfilesRequest $request, ProfileSearchEngine $engine): JsonResponse
    {
        $criteria = $request->criteria();
        $started = microtime(true);
        $result = $engine->search($criteria);
        $duration = (int) round((microtime(true) - $started) * 1000);
        DB::table('search_logs')->insert(['user_id' => $request->user()?->id, 'query' => $criteria['q'] ?? null, 'filters' => json_encode(collect($criteria)->except(['q', 'page', 'per_page'])->all()), 'results_count' => $result['total'], 'duration_ms' => $duration, 'engine' => config('discovery.driver'), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ProfileResource::collection($result['items'])->resolve($request), 'meta' => ['current_page' => $criteria['page'], 'per_page' => $criteria['per_page'], 'total' => $result['total'], 'last_page' => (int) ceil($result['total'] / $criteria['per_page']), 'engine' => config('discovery.driver')]]);
    }
}
