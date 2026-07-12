<?php

namespace App\Http\Controllers\Api\V1\Profiles;

use App\Domain\Auth\Actions\CalculateProfileCompleteness;
use App\Domain\Profiles\Actions\EnsureProfileSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profiles\UpdateAthleteProfileRequest;
use App\Http\Requests\Api\V1\Profiles\UpdateProfileRequest;
use App\Http\Resources\Api\V1\Profiles\ProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

class ProfileController extends Controller
{
    public function show(string $slug): ProfileResource
    {
        $user = $this->find($slug);
        Gate::authorize('view', $user->profile);

        return new ProfileResource($user);
    }

    public function mine(EnsureProfileSlug $slugs): ProfileResource
    {
        $user = request()->user();
        $slugs->execute($user);

        return new ProfileResource($this->load($user->fresh()));
    }

    public function update(UpdateProfileRequest $request, CalculateProfileCompleteness $calculator, EnsureProfileSlug $slugs): ProfileResource
    {
        $user = $request->user();
        Gate::authorize('update', $user->profile);
        if ($request->has('name')) {
            $user->update(['name' => $request->validated('name')]);
        }$user->profile()->update($request->safe()->except('name'));
        $slugs->execute($user->fresh('profile'));
        $calculator->execute($user);

        return new ProfileResource($this->load($user->fresh()));
    }

    public function updateAthlete(UpdateAthleteProfileRequest $request, CalculateProfileCompleteness $calculator): JsonResponse
    {
        $user = $request->user();
        $user->athleteProfile()->updateOrCreate([], $request->validated());
        $calculator->execute($user);

        return response()->json(['message' => 'Athlete profile updated.', 'data' => new ProfileResource($this->load($user->fresh()))]);
    }

    public function photo(Request $request, CalculateProfileCompleteness $calculator): JsonResponse
    {
        $data = $request->validate(['photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:10240']]);
        $user = $request->user();
        $oldPath = $user->profile?->profile_image_path;
        $path = $data['photo']->storePublicly("profiles/{$user->id}", 'public');
        $url = '/storage/'.$path;
        $user->profile()->update(['profile_image_path' => $url]);
        if ($oldPath && str_starts_with($oldPath, '/storage/')) Storage::disk('public')->delete(str($oldPath)->after('/storage/')->value());
        $calculator->execute($user);

        return response()->json(['message' => 'Profile photo updated.', 'data' => ['url' => $url]]);
    }

    public function role(Request $request): ProfileResource
    {
        $data = $request->validate(['role' => ['required', Rule::in(['athlete', 'fan', 'coach', 'scout', 'agent', 'club', 'academy', 'business', 'sponsor'])]]);
        $request->user()->syncRoles([$data['role']]);

        return new ProfileResource($this->load($request->user()->fresh()));
    }

    private function find(string $slug): User
    {
        return $this->load(User::whereHas('profile', fn ($q) => $q->where('slug', $slug))->firstOrFail());
    }

    private function load(User $user): User
    {
        return $user->load('roles', 'profile', 'athleteProfile.sport', 'athleteProfile.taxonomyPosition', 'fanProfile', 'professionalProfile', 'organisationProfile');
    }
}
