<?php

namespace App\Http\Controllers\Api\V1\Onboarding;

use App\Domain\Auth\Actions\CalculateProfileCompleteness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Onboarding\AthleteDetailsRequest;
use App\Http\Requests\Api\V1\Onboarding\FanInterestsRequest;
use App\Http\Requests\Api\V1\Onboarding\LocationRequest;
use App\Http\Requests\Api\V1\Onboarding\RoleRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function role(RoleRequest $request, CalculateProfileCompleteness $calculator): JsonResponse
    {
        $role = $request->validated('role');
        $user = $request->user();
        $user->syncRoles([$role]);
        if (in_array($role, ['coach', 'referee', 'linesman', 'scout', 'agent'], true)) {
            $user->professionalProfile()->firstOrCreate([], ['professional_type' => $role]);
        }
        if (in_array($role, ['club', 'academy', 'business', 'sponsor'], true)) {
            $user->organisationProfile()->firstOrCreate([], ['organisation_name' => $user->name, 'organisation_type' => $role]);
        }
        $calculator->execute($user);

        return $this->result($request);
    }

    public function athleteDetails(AthleteDetailsRequest $request, CalculateProfileCompleteness $calculator): JsonResponse
    {
        $data = $request->safe()->except('bio');
        $request->user()->athleteProfile()->updateOrCreate([], $data);
        if ($request->has('bio')) {
            $request->user()->profile()->updateOrCreate([], ['bio' => $request->validated('bio')]);
        } $calculator->execute($request->user());

        return $this->result($request);
    }

    public function fanInterests(FanInterestsRequest $request, CalculateProfileCompleteness $calculator): JsonResponse
    {
        $request->user()->fanProfile()->updateOrCreate([], $request->validated());
        $calculator->execute($request->user());

        return $this->result($request);
    }

    public function location(LocationRequest $request, CalculateProfileCompleteness $calculator): JsonResponse
    {
        $request->user()->profile()->updateOrCreate([], $request->validated());
        $calculator->execute($request->user());

        return $this->result($request);
    }

    public function completeness(Request $request, CalculateProfileCompleteness $calculator): JsonResponse
    {
        $score = $calculator->execute($request->user());

        return response()->json(['data' => ['percentage' => $score, 'can_continue' => true, 'completed' => $this->sections($request), 'missing' => array_keys(array_filter($this->sections($request), fn ($done) => ! $done))]]);
    }

    public function complete(Request $request, CalculateProfileCompleteness $calculator): JsonResponse
    {
        $calculator->execute($request->user());
        $request->user()->update(['onboarding_completed_at' => now()]);

        return $this->result($request, 'Onboarding completed.');
    }

    private function sections(Request $request): array
    {
        $u = $request->user()->load('roles', 'profile', 'athleteProfile', 'fanProfile');

        return ['account' => true, 'role' => $u->roles->isNotEmpty(), 'role_details' => (bool) ($u->athleteProfile || $u->fanProfile || $u->roles->whereNotIn('name', ['athlete', 'fan'])->isNotEmpty()), 'location' => (bool) ($u->profile?->country || $u->profile?->city || $u->profile?->date_of_birth), 'media' => (bool) $u->profile?->profile_image_path, 'bio' => (bool) $u->profile?->bio];
    }

    private function result(Request $request, string $message = 'Onboarding saved.'): JsonResponse
    {
        return response()->json(['message' => $message, 'data' => new UserResource($request->user()->fresh()->load('roles','profile'))]);
    }
}
