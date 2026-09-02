<?php

namespace App\Http\Controllers\Web;

use App\Domain\Feed\Models\Video;
use App\Domain\Opportunities\Models\Opportunity;
use App\Domain\Sports\Models\Sport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class PublicHomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if (! Schema::hasTable('videos') || ! Schema::hasTable('users') || ! Schema::hasTable('sports') || ! Schema::hasTable('opportunities')) {
            return $this->render(collect(), collect(), collect(), collect());
        }

        $videos = Video::query()
            ->where('status', 'published')->where('visibility', 'public')
            ->where(fn ($query) => $query
                ->whereHas('media', fn ($media) => $media->where('processing_status', 'ready')->where('moderation_status', 'approved'))
                ->orWhereHas('images', fn ($media) => $media->where('processing_status', 'ready')->where('moderation_status', 'approved')))
            ->with(['user.profile', 'user.athleteProfile.sport', 'user.athleteProfile.taxonomyPosition', 'sport', 'media', 'images'])
            ->orderByRaw('(views_count + (likes_count * 3) + (comments_count * 4) + (shares_count * 5)) DESC')
            ->latest('published_at')->limit(10)->get();

        $athletes = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('name', 'athlete'))
            ->whereHas('profile', fn ($query) => $query->where('is_public', true)->whereNotNull('slug'))
            ->with(['profile', 'athleteProfile.sport', 'athleteProfile.taxonomyPosition'])
            ->withCount(['followers', 'videos' => fn ($query) => $query->where('status', 'published')->where('visibility', 'public')])
            ->orderByDesc('videos_count')->orderByDesc('followers_count')->limit(8)->get();

        $sports = Sport::query()->where('is_active', true)
            ->withCount(['positions', 'athletes as athletes_count' => fn ($query) => $query->whereHas('user.profile', fn ($profile) => $profile->where('is_public', true))])
            ->orderByDesc('athletes_count')->orderBy('name')->limit(12)->get();

        $opportunities = Opportunity::query()->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('deadline')->orWhere('deadline', '>=', now()))
            ->with(['poster.organisationProfile', 'sport'])->orderBy('deadline')->limit(4)->get();

        return $this->render($videos, $athletes, $sports, $opportunities);
    }

    private function render($videos, $athletes, $sports, $opportunities): Response
    {
        $hasDataTables = Schema::hasTable('videos');

        return Inertia::render('Public/Home', [
            'highlights' => $videos->map(fn (Video $video) => $this->video($video))->values(),
            'athletes' => $athletes->map(fn (User $user) => [
                'name' => $user->name, 'slug' => $user->profile?->slug, 'image' => $user->profile?->profile_image_path,
                'sport' => $user->athleteProfile?->sport?->name ?? 'Athlete', 'position' => $user->athleteProfile?->taxonomyPosition?->name,
                'location' => collect([$user->profile?->city, $user->profile?->province])->filter()->join(', '),
                'followers' => $user->followers_count, 'highlights' => $user->videos_count,
            ])->values(),
            'sports' => $sports->map(fn (Sport $sport) => ['name' => $sport->name, 'slug' => $sport->slug, 'athletes' => $sport->athletes_count])->values(),
            'opportunities' => $opportunities->map(fn (Opportunity $opportunity) => [
                'id' => $opportunity->public_id, 'title' => $opportunity->title, 'type' => $opportunity->type,
                'sport' => $opportunity->sport?->name, 'location' => $opportunity->is_remote ? 'Remote' : collect([$opportunity->city, $opportunity->province])->filter()->join(', '),
                'host' => $opportunity->poster?->organisationProfile?->organisation_name ?? $opportunity->poster?->name,
                'deadline' => $opportunity->deadline?->toDateString(),
            ])->values(),
            'stats' => [
                'athletes' => $hasDataTables ? User::where('status', 'active')->whereHas('roles', fn ($query) => $query->where('name', 'athlete'))->count() : 0,
                'highlights' => $hasDataTables ? Video::where('status', 'published')->where('visibility', 'public')->count() : 0,
                'sports' => $hasDataTables ? Sport::where('is_active', true)->count() : 0,
                'opportunities' => $hasDataTables ? Opportunity::where('status', 'published')->where(fn ($query) => $query->whereNull('deadline')->orWhere('deadline', '>=', now()))->count() : 0,
            ],
        ])->withViewData('seo', [
            'title' => 'SportsUniverse | Where Sports Talent Meets Opportunity',
            'description' => 'Discover athletes, watch sports highlights and connect talent with scouts, clubs, coaches and sporting opportunities on SportsUniverse.',
            'image' => $videos->first()?->images->first()?->public_id ? route('media.public', $videos->first()->images->first()) : url(config('seo.image')),
        ]);
    }

    private function video(Video $video): array
    {
        $cover = $video->images->first(fn ($image) => (bool) $image->pivot->is_cover) ?? $video->images->first();

        return [
            'id' => $video->public_id, 'caption' => $video->caption, 'video' => $video->media ? route('videos.stream', $video) : null,
            'cover' => $cover ? route('media.public', $cover) : null, 'sport' => $video->sport?->name ?? $video->user->athleteProfile?->sport?->name,
            'location' => $video->location_name ?: $video->user->profile?->city, 'views' => $video->views_count, 'likes' => $video->likes_count,
            'athlete' => ['name' => $video->user->name, 'slug' => $video->user->profile?->slug, 'image' => $video->user->profile?->profile_image_path, 'position' => $video->user->athleteProfile?->taxonomyPosition?->name],
        ];
    }
}
