<?php

namespace App\Http\Controllers\Api\V1\Profiles;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Profiles\ProfileResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProfileConnectionController extends Controller
{
    public function followers(Request $request, User $user): AnonymousResourceCollection
    {
        return $this->connections($request, $user, 'followers');
    }

    public function following(Request $request, User $user): AnonymousResourceCollection
    {
        return $this->connections($request, $user, 'following');
    }

    private function connections(Request $request, User $user, string $relation): AnonymousResourceCollection
    {
        Gate::authorize('view', $user->profile);
        $profiles = $user->{$relation}()
            ->whereHas('profile', fn ($query) => $query->where('is_public', true))
            ->with('roles', 'profile', 'athleteProfile.sport', 'athleteProfile.taxonomyPosition', 'professionalProfile', 'organisationProfile')
            ->withCount(['followers', 'following'])
            ->orderByPivot('created_at', 'desc')
            ->paginate(min($request->integer('per_page', 30), 50));

        return ProfileResource::collection($profiles);
    }
}
