<?php

namespace App\Http\Controllers\Api\V1\Discovery;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Profiles\ProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profiles = $request->user()->savedProfiles()
            ->whereHas('profile', fn ($query) => $query->where('is_public', true))
            ->with('roles', 'profile', 'athleteProfile.sport', 'athleteProfile.taxonomyPosition', 'professionalProfile', 'organisationProfile')
            ->orderByPivot('created_at', 'desc')->get();

        return response()->json(['data' => ProfileResource::collection($profiles)->resolve($request)]);
    }

    public function store(Request $request, User $profile): JsonResponse
    {
        abort_if($request->user()->is($profile), 422, 'You cannot save your own profile.');
        abort_unless($profile->profile?->is_public, 404);
        $request->user()->savedProfiles()->syncWithoutDetaching([$profile->id]);

        return response()->json(['message' => 'Profile saved.', 'data' => ['saved' => true]], 201);
    }

    public function destroy(Request $request, User $profile): JsonResponse
    {
        $request->user()->savedProfiles()->detach($profile->id);

        return response()->json(['message' => 'Profile removed.', 'data' => ['saved' => false]]);
    }
}
