<?php

namespace App\Http\Controllers\Api\V1\Opportunities;

use App\Domain\Opportunities\Models\Opportunity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SavedOpportunityController extends Controller
{
    public function store(Request $request, Opportunity $opportunity): JsonResponse
    {
        Gate::authorize('view', $opportunity);
        $request->user()->belongsToMany(Opportunity::class, 'saved_opportunities')->syncWithoutDetaching([$opportunity->id]);

        return response()->json(['data' => ['saved' => true]]);
    }

    public function destroy(Request $request, Opportunity $opportunity): JsonResponse
    {
        $request->user()->belongsToMany(Opportunity::class, 'saved_opportunities')->detach($opportunity);

        return response()->json(['data' => ['saved' => false]]);
    }
}
