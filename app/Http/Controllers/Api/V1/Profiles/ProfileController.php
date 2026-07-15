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

    public function updateProfessional(Request $request, CalculateProfileCompleteness $calculator): JsonResponse
    {
        $data = $request->validate([
            'professional_type' => ['required', Rule::in(['coach', 'referee', 'linesman', 'scout', 'agent'])],
            'specialisation' => ['nullable', 'string', 'max:160'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'certifications' => ['nullable', 'array', 'max:30'],
            'certifications.*' => ['string', 'max:255'],
            'is_available' => ['required', 'boolean'],
        ]);
        $user = $request->user();
        abort_unless($user->hasAnyRole(['coach', 'referee', 'linesman', 'scout', 'agent']), 403);
        $user->professionalProfile()->updateOrCreate([], $data);
        $user->profile()->update(['is_available' => $data['is_available']]);
        $calculator->execute($user);

        return response()->json(['message' => 'Professional profile updated.', 'data' => new ProfileResource($this->load($user->fresh()))]);
    }

    public function updateOrganisation(Request $request, CalculateProfileCompleteness $calculator): JsonResponse
    {
        $data = $request->validate([
            'organisation_name' => ['required', 'string', 'max:160'],
            'organisation_type' => ['required', Rule::in(['club', 'academy', 'business', 'sponsor'])],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'services' => ['nullable', 'array', 'max:30'],
            'services.*' => ['string', 'max:160'],
        ]);
        $user = $request->user();
        abort_unless($user->hasAnyRole(['club', 'academy', 'business', 'sponsor']), 403);
        $user->organisationProfile()->updateOrCreate([], $data);
        $calculator->execute($user);

        return response()->json(['message' => 'Organisation profile updated.', 'data' => new ProfileResource($this->load($user->fresh()))]);
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
        $data = $request->validate(['role' => ['required', Rule::in(['athlete', 'fan', 'coach', 'referee', 'linesman', 'scout', 'agent', 'club', 'academy', 'business', 'sponsor'])]]);
        $request->user()->syncRoles([$data['role']]);
        if (in_array($data['role'], ['coach','referee','linesman','scout','agent'], true)) $request->user()->professionalProfile()->firstOrCreate([], ['professional_type'=>$data['role']]);
        if (in_array($data['role'], ['club','academy','business','sponsor'], true)) $request->user()->organisationProfile()->firstOrCreate([], ['organisation_name'=>$request->user()->name,'organisation_type'=>$data['role']]);

        return new ProfileResource($this->load($request->user()->fresh()));
    }

    private function find(string $slug): User
    {
        return $this->load(User::whereHas('profile', fn ($q) => $q->where('slug', $slug))->firstOrFail());
    }

    private function load(User $user): User
    {
        return $user->load('roles', 'profile', 'athleteProfile.sport', 'athleteProfile.taxonomyPosition', 'careerEntries', 'achievements', 'athleteStatistics', 'fanProfile', 'professionalProfile', 'organisationProfile');
    }
}
