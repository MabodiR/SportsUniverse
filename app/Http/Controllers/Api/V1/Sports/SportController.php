<?php

namespace App\Http\Controllers\Api\V1\Sports;

use App\Domain\Sports\Models\Sport;
use App\Domain\Sports\Models\Position;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SportController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return JsonResource::collection(Sport::query()->where('is_active', true)->with(['positions' => fn ($q) => $q->where('is_active', true)->orderBy('name')])->orderBy('sort_order')->orderBy('name')->get());
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $this->admin($request);
        // Keep management reads independent from large analytics tables. This
        // endpoint must stay fast even when videos contain millions of rows.
        $sports = Sport::query()
            ->with(['positions' => fn ($query) => $query->orderBy('name')])
            ->orderBy('sort_order')->orderBy('name')->get();

        return response()->json(['data' => $sports->map(fn (Sport $sport) => [
            'id' => $sport->id, 'name' => $sport->name, 'slug' => $sport->slug, 'is_active' => $sport->is_active, 'sort_order' => $sport->sort_order,
            'usage' => ['athletes' => null, 'videos' => null, 'opportunities' => null],
            'positions' => $sport->positions->map(fn (Position $position) => ['id' => $position->id, 'name' => $position->name, 'slug' => $position->slug, 'is_active' => $position->is_active, 'athletes' => null]),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->admin($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:sports,name'], 'sort_order' => ['nullable', 'integer', 'between:0,65535'], 'is_active' => ['nullable', 'boolean']]);
        $sport = Sport::create([...$data, 'slug' => $this->uniqueSportSlug($data['name']), 'sort_order' => $data['sort_order'] ?? 0, 'is_active' => $data['is_active'] ?? true]);
        return response()->json(['message' => 'Sport added.', 'data' => $sport->load('positions')], 201);
    }

    public function importCatalogue(Request $request): JsonResponse
    {
        $this->admin($request);
        $sportsAdded = 0; $positionsAdded = 0;
        foreach (config('sports_catalogue', []) as $name => $positions) {
            $sport = Sport::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'is_active' => true, 'sort_order' => array_search($name, array_keys(config('sports_catalogue')), true) * 10]);
            if ($sport->wasRecentlyCreated) $sportsAdded++;
            foreach ($positions as $positionName) {
                $position = $sport->positions()->firstOrCreate(['slug' => Str::slug($positionName)], ['name' => $positionName, 'is_active' => true]);
                if ($position->wasRecentlyCreated) $positionsAdded++;
            }
        }
        return response()->json(['message' => "Catalogue updated: {$sportsAdded} sports and {$positionsAdded} positions added."]);
    }

    public function update(Request $request, Sport $sport): JsonResponse
    {
        $this->admin($request);
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('sports', 'name')->ignore($sport)], 'sort_order' => ['sometimes', 'integer', 'between:0,65535'], 'is_active' => ['sometimes', 'boolean']]);
        if (isset($data['name']) && $data['name'] !== $sport->name) $data['slug'] = $this->uniqueSportSlug($data['name'], $sport->id);
        $sport->update($data);
        return response()->json(['message' => 'Sport updated.', 'data' => $sport->fresh()->load('positions')]);
    }

    public function storePosition(Request $request, Sport $sport): JsonResponse
    {
        $this->admin($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'is_active' => ['nullable', 'boolean']]);
        $slug = Str::slug($data['name']);
        abort_if($sport->positions()->where('slug', $slug)->exists(), 422, 'This position already exists for '.$sport->name.'.');
        $position = $sport->positions()->create(['name' => $data['name'], 'slug' => $slug, 'is_active' => $data['is_active'] ?? true]);
        return response()->json(['message' => 'Position added.', 'data' => $position], 201);
    }

    public function updatePosition(Request $request, Position $position): JsonResponse
    {
        $this->admin($request);
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:100'], 'is_active' => ['sometimes', 'boolean']]);
        if (isset($data['name']) && $data['name'] !== $position->name) {
            $data['slug'] = Str::slug($data['name']);
            abort_if(Position::where('sport_id', $position->sport_id)->where('slug', $data['slug'])->whereKeyNot($position->id)->exists(), 422, 'This position already exists for the sport.');
        }
        $position->update($data);
        return response()->json(['message' => 'Position updated.', 'data' => $position->fresh()]);
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'system_admin', 'super_admin']), 403);
    }

    private function uniqueSportSlug(string $name, ?int $except = null): string
    {
        $base = Str::slug($name) ?: 'sport'; $slug = $base; $suffix = 2;
        while (Sport::where('slug', $slug)->when($except, fn ($query) => $query->whereKeyNot($except))->exists()) $slug = $base.'-'.$suffix++;
        return $slug;
    }
}
