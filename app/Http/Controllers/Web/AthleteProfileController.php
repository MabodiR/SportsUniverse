<?php

namespace App\Http\Controllers\Web;

use App\Domain\Analytics\Actions\RecordProfileView;
use App\Domain\Feed\Models\Video;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AthleteProfileController extends Controller
{
    public function __invoke(string $slug, RecordProfileView $record): Response
    {
        $user = User::query()
            ->whereHas('profile', fn ($query) => $query->where('slug', $slug))
            ->with(['profile', 'athleteProfile.sport', 'athleteProfile.taxonomyPosition', 'careerEntries.sport', 'careerEntries.position', 'achievements', 'athleteStatistics'])
            ->withCount(['followers', 'following', 'videos' => fn ($query) => $query->where('status', 'published')])
            ->firstOrFail();

        Gate::authorize('view', $user->profile);

        if (auth()->check() && ! auth()->user()->is($user)) {
            $record->execute(auth()->user(), $user, 'profile');
        }

        $videos = Video::query()
            ->whereBelongsTo($user)
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->with('media', 'sport')
            ->latest('published_at')
            ->get()
            ->map(fn (Video $video) => [
                'id' => $video->public_id,
                'caption' => $video->caption,
                'hashtags' => $video->hashtags ?? [],
                'sport' => $video->sport?->name,
                'views' => $video->views_count,
                'likes' => $video->likes_count,
                'duration_ms' => $video->media?->duration_ms,
                'url' => $video->media ? route('videos.stream', $video) : null,
            ]);

        $seoTitle = $user->name.' — '.implode(' · ', array_filter([$user->athleteProfile?->sport?->name, $user->athleteProfile?->taxonomyPosition?->name, $user->profile->city])).' | SportsUniverse';
        $seoDescription = str($user->profile->bio ?: 'View '.$user->name."'s sports profile, highlights and achievements on SportsUniverse.")->squish()->limit(160)->value();
        return Inertia::render('Profiles/Show', [
            'athlete' => [
                'id' => $user->id,
                'name' => $user->name,
                'slug' => $user->profile->slug,
                'bio' => $user->profile->bio,
                'city' => $user->profile->city,
                'province' => $user->profile->province,
                'country' => $user->profile->country,
                'available' => $user->profile->is_available,
                'image' => $user->profile->profile_image_path,
                'cover' => $user->profile->cover_image_path,
                'sport' => $user->athleteProfile?->sport?->name,
                'position' => $user->athleteProfile?->taxonomyPosition?->name,
                'club' => $user->athleteProfile?->club_name,
                'level' => $user->athleteProfile?->playing_level,
                'dominant_side' => $user->athleteProfile?->dominant_side,
                'height_cm' => $user->athleteProfile?->height_cm,
                'weight_kg' => $user->athleteProfile?->weight_kg,
                'followers' => $user->followers_count,
                'following' => $user->following_count,
                'videos_count' => $user->videos_count,
                'profile_views' => $user->profile->views_count,
                'is_following' => auth()->check() && auth()->user()->following()->whereKey($user->id)->exists(),
                'is_saved' => auth()->check() && auth()->user()->savedProfiles()->whereKey($user->id)->exists(),
                'career_history' => $user->careerEntries->sortByDesc('started_on')->values()->map(fn ($entry) => [
                    'team_name' => $entry->team_name, 'sport' => $entry->sport?->name,
                    'role' => $entry->position?->name ?? $entry->role, 'level' => $entry->level,
                    'started_on' => $entry->started_on?->toDateString(), 'ended_on' => $entry->ended_on?->toDateString(),
                    'is_current' => $entry->is_current, 'description' => $entry->description,
                ]),
                'achievements' => $user->achievements->sortByDesc('achieved_on')->values()->map(fn ($achievement) => [
                    'title' => $achievement->title, 'issuer' => $achievement->issuer,
                    'achieved_on' => $achievement->achieved_on?->toDateString(), 'description' => $achievement->description,
                ]),
                'statistics' => $user->athleteStatistics->sortByDesc('season')->values()->map(fn ($statistic) => [
                    'season' => $statistic->season, 'competition' => $statistic->competition,
                    'name' => $statistic->name, 'value' => $statistic->value, 'unit' => $statistic->unit,
                ]),
            ],
            'videos' => $videos,
            'seo' => ['title'=>$seoTitle,'description'=>$seoDescription,'image'=>$user->profile->profile_image_path ? url($user->profile->profile_image_path) : url(config('seo.image'))],
        ])->withViewData('seo',['title'=>$seoTitle,'description'=>$seoDescription,'image'=>$user->profile->profile_image_path ? url($user->profile->profile_image_path) : url(config('seo.image')),'type'=>'profile']);
    }
}
