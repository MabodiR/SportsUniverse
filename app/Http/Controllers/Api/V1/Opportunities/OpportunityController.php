<?php

namespace App\Http\Controllers\Api\V1\Opportunities;

use App\Domain\Opportunities\Models\Opportunity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Opportunities\StoreOpportunityRequest;
use App\Http\Requests\Api\V1\Opportunities\UpdateOpportunityRequest;
use App\Http\Resources\Api\V1\Opportunities\OpportunityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class OpportunityController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Opportunity::query()->where('status', 'published')->where(fn ($q) => $q->whereNull('deadline')->orWhere('deadline', '>', now()))->with('poster.profile', 'poster.organisationProfile', 'sport', 'position')->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))->when($request->filled('sport_id'), fn ($q) => $q->where('sport_id', $request->integer('sport_id')))->when($request->filled('position_id'), fn ($q) => $q->where('position_id', $request->integer('position_id')))->when($request->filled('country'), fn ($q) => $q->where('country', $request->string('country')))->when($request->filled('city'), fn ($q) => $q->where('city', $request->string('city')))->when($request->boolean('remote'), fn ($q) => $q->where('is_remote', true))->when($request->filled('q'), fn ($query) => $query->where(fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%')->orWhere('description', 'like', '%'.$request->string('q').'%')))->orderBy('deadline')->latest('published_at');
        $page = $query->paginate(min($request->integer('per_page', 20), 50));
        $this->decorate($page->getCollection(), $request);

        return OpportunityResource::collection($page);
    }

    public function store(StoreOpportunityRequest $request): JsonResponse
    {
        $publish = $request->boolean('publish');
        $opportunity = $request->user()->hasMany(Opportunity::class, 'posted_by_id')->create([...$request->safe()->except('publish'), 'public_id' => (string) Str::ulid(), 'is_remote' => $request->boolean('is_remote'), 'status' => $publish ? 'published' : 'draft', 'published_at' => $publish ? now() : null]);

        return response()->json(['message' => $publish ? 'Opportunity published.' : 'Opportunity draft created.', 'data' => new OpportunityResource($this->load($opportunity))], 201);
    }

    public function show(Request $request, Opportunity $opportunity): OpportunityResource
    {
        Gate::authorize('view', $opportunity);
        $this->decorate(collect([$opportunity]), $request);

        return new OpportunityResource($this->load($opportunity));
    }

    public function update(UpdateOpportunityRequest $request, Opportunity $opportunity): OpportunityResource
    {
        $data = $request->safe()->except('publish');
        if ($request->has('is_remote')) {
            $data['is_remote'] = $request->boolean('is_remote');
        }if ($request->boolean('publish') && $opportunity->status === 'draft') {
            $data = [...$data, 'status' => 'published', 'published_at' => now()];
        }$opportunity->update($data);

        return new OpportunityResource($this->load($opportunity->fresh()));
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        return OpportunityResource::collection($request->user()->hasMany(Opportunity::class, 'posted_by_id')->with('poster.profile', 'poster.organisationProfile', 'sport', 'position')->latest()->paginate(20));
    }

    public function destroy(Opportunity $opportunity): JsonResponse
    {
        Gate::authorize('delete', $opportunity);
        abort_if($opportunity->applications()->exists(), 409, 'Opportunities with applications must be cancelled instead of deleted.');
        $opportunity->delete();

        return response()->json(['message' => 'Opportunity deleted.']);
    }

    public function cancel(Opportunity $opportunity): JsonResponse
    {
        Gate::authorize('update', $opportunity);
        $opportunity->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Opportunity cancelled.']);
    }

    public function decorate($items, Request $request): void
    {
        $user = $request->user();
        if (! $user) {
            return;
        }$ids = $items->pluck('id');
        $saved = DB::table('saved_opportunities')->where('user_id', $user->id)->whereIn('opportunity_id', $ids)->pluck('opportunity_id')->flip();
        $applied = DB::table('opportunity_applications')->where('user_id', $user->id)->whereIn('opportunity_id', $ids)->pluck('opportunity_id')->flip();
        foreach ($items as $item) {
            $item->saved_by_viewer = $saved->has($item->id);
            $item->applied_by_viewer = $applied->has($item->id);
        }
    }

    private function load(Opportunity $opportunity): Opportunity
    {
        return $opportunity->load('poster.profile','poster.organisationProfile','sport','position');
    }
}
