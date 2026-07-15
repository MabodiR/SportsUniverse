<?php

namespace App\Http\Controllers\Api\V1\Discovery;

use App\Domain\Feed\Models\Video;
use App\Domain\Opportunities\Models\Opportunity;
use App\Domain\Sports\Models\Sport;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Profiles\ProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WomenInSportsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $sportId = $request->validate(['sport_id' => ['nullable', 'integer', 'exists:sports,id']])['sport_id'] ?? null;
        $profiles = User::query()->where('status', 'active')->whereHas('profile', fn ($query) => $query->where('is_public', true)->where('gender', 'female'))
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['athlete', 'coach', 'referee', 'linesman', 'scout', 'agent']))
            ->when($sportId, fn ($query) => $query->whereHas('athleteProfile', fn ($athlete) => $athlete->where('sport_id', $sportId)))
            ->with('roles', 'profile', 'athleteProfile.sport', 'athleteProfile.taxonomyPosition', 'professionalProfile', 'organisationProfile')->withCount('followers')->orderByDesc('followers_count')->limit(18)->get();
        $videos = Video::query()->where('status', 'published')->where('visibility', 'public')->whereHas('user.profile', fn ($query) => $query->where('is_public', true)->where('gender', 'female'))
            ->when($sportId, fn ($query) => $query->where('sport_id', $sportId))->with('user.profile', 'user.athleteProfile.sport', 'sport', 'images', 'media')->latest('published_at')->limit(12)->get()
            ->map(fn (Video $video) => ['id' => $video->public_id, 'caption' => $video->caption, 'hashtags' => $video->hashtags ?? [], 'views' => $video->views_count, 'likes' => $video->likes_count, 'creator' => ['name' => $video->user->name, 'slug' => $video->user->profile?->slug], 'sport' => $video->sport?->name ?? $video->user->athleteProfile?->sport?->name, 'thumbnail' => $video->images->first() ? route('media.public', $video->images->first()) : null, 'stream' => $video->media ? route('videos.stream', $video) : null]);
        $opportunities = Opportunity::query()->where('status', 'published')->where(fn ($query) => $query->whereNull('deadline')->orWhere('deadline', '>', now()))
            ->where(fn ($query) => $query->whereRaw('LOWER(title) LIKE ?', ['%women%'])->orWhereRaw('LOWER(title) LIKE ?', ['%female%'])->orWhereRaw('LOWER(title) LIKE ?', ['%girls%'])->orWhereRaw('LOWER(description) LIKE ?', ['%women%'])->orWhereRaw('LOWER(description) LIKE ?', ['%female%'])->orWhereRaw('LOWER(description) LIKE ?', ['%girls%'])->orWhereRaw('LOWER(requirements) LIKE ?', ['%women%'])->orWhereRaw('LOWER(requirements) LIKE ?', ['%female%'])->orWhereRaw('LOWER(requirements) LIKE ?', ['%girls%']))
            ->when($sportId, fn ($query) => $query->where('sport_id', $sportId))->with('poster.organisationProfile', 'sport')->orderBy('deadline')->limit(12)->get()
            ->map(fn (Opportunity $item) => ['id' => $item->public_id, 'title' => $item->title, 'type' => $item->type, 'description' => $item->description, 'poster' => $item->poster->organisationProfile?->organisation_name ?? $item->poster->name, 'sport' => $item->sport?->name, 'city' => $item->city, 'is_remote' => $item->is_remote, 'deadline' => $item->deadline]);
        $sports = Sport::query()->whereHas('athleteProfiles.user.profile', fn ($query) => $query->where('gender', 'female')->where('is_public', true))->withCount(['athleteProfiles' => fn ($query) => $query->whereHas('user.profile', fn ($profile) => $profile->where('gender', 'female')->where('is_public', true))])->orderByDesc('athlete_profiles_count')->get(['id', 'name', 'slug']);
        $saved = $request->user()->savedProfiles()->pluck('users.id')->all();
        $profileData = ProfileResource::collection($profiles)->resolve($request);
        foreach ($profileData as &$profile) $profile['saved'] = in_array($profile['id'], $saved, true);

        return response()->json(['data' => ['profiles' => $profileData, 'videos' => $videos, 'opportunities' => $opportunities, 'sports' => $sports], 'stats' => ['profiles' => $profiles->count(), 'videos' => $videos->count(), 'opportunities' => $opportunities->count()]]);
    }
}
