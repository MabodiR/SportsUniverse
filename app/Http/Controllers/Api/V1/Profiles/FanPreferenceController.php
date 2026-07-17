<?php

namespace App\Http\Controllers\Api\V1\Profiles;

use App\Domain\Auth\Actions\CalculateProfileCompleteness;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FanPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('fan'), 403, 'Fan preferences require a fan account.');
        $profile = $request->user()->fanProfile()->firstOrCreate([], ['interested_sports' => []]);

        return response()->json(['data' => [
            'interested_sports' => $profile->interested_sports ?? [],
            'favourites' => $this->decodeFavourites($profile->favourites),
        ]]);
    }

    public function update(Request $request, CalculateProfileCompleteness $calculator): JsonResponse
    {
        abort_unless($request->user()->hasRole('fan'), 403, 'Fan preferences require a fan account.');
        $data = $request->validate([
            'interested_sports' => ['required', 'array', 'min:1', 'max:20'],
            'interested_sports.*' => ['string', 'max:100', 'distinct'],
            'favourites' => ['required', 'array'],
            'favourites.athletes' => ['nullable', 'array', 'max:30'],
            'favourites.athletes.*' => ['string', 'max:120', 'distinct'],
            'favourites.teams' => ['nullable', 'array', 'max:30'],
            'favourites.teams.*' => ['string', 'max:120', 'distinct'],
            'favourites.clubs' => ['nullable', 'array', 'max:30'],
            'favourites.clubs.*' => ['string', 'max:120', 'distinct'],
        ]);
        $favourites = collect($data['favourites'])->map(fn ($items) => collect($items ?? [])->map(fn ($item) => trim($item))->filter()->unique(fn ($item) => mb_strtolower($item))->values()->all())->all();
        $request->user()->fanProfile()->updateOrCreate([], ['interested_sports' => $data['interested_sports'], 'favourites' => json_encode($favourites)]);
        $calculator->execute($request->user());

        return response()->json(['message' => 'Fan preferences updated.', 'data' => ['interested_sports' => $data['interested_sports'], 'favourites' => $favourites]]);
    }

    private function decodeFavourites(?string $value): array
    {
        $decoded = $value ? json_decode($value, true) : null;
        if (is_array($decoded)) return ['athletes' => array_values($decoded['athletes'] ?? []), 'teams' => array_values($decoded['teams'] ?? []), 'clubs' => array_values($decoded['clubs'] ?? [])];

        return ['athletes' => [], 'teams' => $value ? [$value] : [], 'clubs' => []];
    }
}
