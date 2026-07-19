<?php

namespace App\Http\Controllers\Web;

use App\Domain\Feed\Models\Video;
use App\Domain\Feed\Models\FeedSetting;
use App\Domain\Feed\Services\SafeSponsoredPostProvider;
use App\Domain\Feed\Services\ApplyFeedPreferences;
use App\Domain\Feed\Services\RankFeed;
use App\Domain\Feed\Services\RecommendationFeed;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FeedController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return $this->renderFeed($request);
    }

    public function location(Request $request, string $location): Response
    {
        return $this->renderFeed($request, location: $location);
    }

    public function sport(Request $request, string $sport): Response
    {
        return $this->renderFeed($request, sport: $sport);
    }

    public function position(Request $request, string $position): Response
    {
        return $this->renderFeed($request, position: $position);
    }

    public function following(Request $request): Response
    {
        return $this->renderFeed($request, null, true);
    }

    public function saved(Request $request): Response
    {
        $videos = Video::query()->whereHas('savers', fn ($query) => $query->whereKey($request->user()->id))
            ->where('status', 'published')->where('visibility', 'public')
            ->latest('published_at')
            ->with('user.profile', 'user.athleteProfile.sport', 'user.athleteProfile.taxonomyPosition', 'sport', 'media', 'images')
            ->get();

        return Inertia::render('Saved/Index', [
            'videos' => $this->mapVideos($videos, $request->user()),
            'count' => $videos->count(),
        ]);
    }

    private function renderFeed(Request $request, ?string $location = null, bool $following = false, ?string $sport = null, ?string $position = null): Response
    {
        $settings = FeedSetting::current();
        $fanSports = ! $following && $settings->use_fan_sports && $request->user()?->hasRole('fan') ? ($request->user()->fanProfile?->interested_sports ?? []) : [];
        $fanSports = collect($fanSports)->filter()->map(fn ($name) => mb_strtolower((string) $name))->values()->all();
        $query = app(ApplyFeedPreferences::class)->execute(Video::query()->select('videos.*')->where('status', 'published')->where('visibility', 'public'), $request->user())
            ->where(fn ($post) => $post
                ->whereHas('media', fn ($media) => $media->where('processing_status', 'ready')->where('moderation_status', 'approved'))
                ->orWhereHas('images', fn ($media) => $media->where('processing_status', 'ready')->where('moderation_status', 'approved')))
            ->when($fanSports, fn ($query) => $query->where(function ($videos) use ($fanSports) { $videos->whereHas('sport', fn ($sport) => $sport->whereIn(DB::raw('LOWER(name)'), $fanSports))->orWhereHas('user.athleteProfile.sport', fn ($sport) => $sport->whereIn(DB::raw('LOWER(name)'), $fanSports)); }))
            ->when($location, fn ($query) => $query->whereRaw('LOWER(location_name) = ?', [mb_strtolower($location)]))
            ->when($sport, fn ($query) => $query->where(function ($videoQuery) use ($sport) {
                $videoQuery->whereHas('sport', fn ($sportQuery) => $sportQuery->whereRaw('LOWER(name) = ?', [mb_strtolower($sport)]))
                    ->orWhereHas('user.athleteProfile.sport', fn ($sportQuery) => $sportQuery->whereRaw('LOWER(name) = ?', [mb_strtolower($sport)]));
            }))
            ->when($position, fn ($query) => $query->whereHas('user.athleteProfile.taxonomyPosition', fn ($positionQuery) => $positionQuery->whereRaw('LOWER(name) = ?', [mb_strtolower($position)])))
            ->when($following, fn ($query) => $query->whereIn('user_id', $request->user()->following()->select('users.id')))
            ->with('user.profile', 'user.athleteProfile.sport', 'user.athleteProfile.taxonomyPosition', 'sport', 'media', 'images')
            ->when($request->user(), fn ($query, $user) => $query->withExists([
                'likers as liked_by_viewer' => fn ($likes) => $likes->whereKey($user->id),
                'savers as saved_by_viewer' => fn ($saves) => $saves->whereKey($user->id),
            ]));
        if ($following) {
            $query->orderByDesc('videos.published_at')->orderByDesc('videos.id');
        } else {
            $precomputed = $request->user() ? app(RecommendationFeed::class)->ids($request->user()) : collect();
            app(RankFeed::class)->execute($query, $request->user(), $precomputed);
        }
        $videos = $query->limit($settings->page_size)->get();
        if (! $following && ! $location && ! $sport && ! $position) {
            $videos = app(SafeSponsoredPostProvider::class)->insert($videos, $request);
        }
        $trendingSince = now()->subDays(7);
        $suggestions = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'athlete'))
            ->when($request->user(), fn ($query) => $query->whereKeyNot($request->user()->id))
            ->whereHas('videos', fn ($query) => $query->where('status', 'published')->where('published_at', '>=', $trendingSince))
            ->with('profile', 'athleteProfile.sport')->withCount('followers')
            ->withSum(['videos as recent_views' => fn ($query) => $query->where('status', 'published')->where('published_at', '>=', $trendingSince)], 'views_count')
            ->withSum(['videos as recent_likes' => fn ($query) => $query->where('status', 'published')->where('published_at', '>=', $trendingSince)], 'likes_count')
            ->withSum(['videos as recent_comments' => fn ($query) => $query->where('status', 'published')->where('published_at', '>=', $trendingSince)], 'comments_count')
            ->withSum(['videos as recent_shares' => fn ($query) => $query->where('status', 'published')->where('published_at', '>=', $trendingSince)], 'shares_count')
            ->orderByDesc('recent_views')
            ->limit(40)->get()->sortByDesc(fn (User $user) => (int) $user->recent_views + ((int) $user->recent_likes * 3) + ((int) $user->recent_comments * 4) + ((int) $user->recent_shares * 5))->take(8)->values()->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'slug' => $user->profile?->slug,
            'sport' => $user->athleteProfile?->sport?->name ?? 'Athlete',
            'followers' => $user->followers_count,
            'trend_score' => (int) $user->recent_views + ((int) $user->recent_likes * 3) + ((int) $user->recent_comments * 4) + ((int) $user->recent_shares * 5),
        ]);

        return Inertia::render('Feed/Index', [
            'videos' => $this->mapVideos($videos, $request->user()),
            'suggestions' => $suggestions,
            'location' => $location,
            'sportFilter' => $sport,
            'positionFilter' => $position,
            'mode' => $following ? 'following' : 'for-you',
        ]);
    }

    private function mapVideos($videos, ?User $viewer): array
    {
        $followedIds = $viewer?->following()->pluck('users.id')->flip() ?? collect();
        $videoIds = $videos->pluck('id');
        $liked = $viewer ? DB::table('video_likes')->where('user_id', $viewer->id)->whereIn('video_id', $videoIds)->pluck('video_id')->flip() : collect();
        $saved = $viewer ? DB::table('saved_videos')->where('user_id', $viewer->id)->whereIn('video_id', $videoIds)->pluck('video_id')->flip() : collect();
        $reposted = $viewer ? DB::table('video_shares')->where('user_id', $viewer->id)->where('channel', 'repost')->whereIn('video_id', $videoIds)->pluck('video_id')->flip() : collect();

        return $videos->map(fn (Video $video) => [
            'id' => $video->public_id,
            'caption' => $video->caption,
            'visibility' => $video->visibility,
            'url' => $video->media ? route('videos.stream', $video) : null,
            'type' => $video->media ? ($video->images->isNotEmpty() ? 'carousel' : 'video') : 'images',
            'images' => $video->images->map(fn ($image) => ['id' => $image->public_id, 'url' => route('media.public', $image), 'is_cover' => (bool) $image->pivot->is_cover])->values(),
            'cover_url' => $video->images->first(fn ($image) => (bool) $image->pivot->is_cover) ? route('media.public', $video->images->first(fn ($image) => (bool) $image->pivot->is_cover)) : null,
            'sport' => $video->sport?->only(['id','name','slug']),
            'location' => ['name'=>$video->location_name,'latitude'=>$video->latitude,'longitude'=>$video->longitude],
            'comments_enabled' => (bool)$video->comments_enabled,
            'hashtags' => $video->hashtags ?? [],
            'creator' => [
                'id' => $video->user->id,
                'name' => $video->user->name,
                'slug' => $video->user->profile?->slug,
                'profile_image' => $video->user->profile?->profile_image_path,
                'sport' => $video->user->athleteProfile?->sport?->name ?? $video->sport?->name,
                'position' => $video->user->athleteProfile?->taxonomyPosition?->name,
                'city' => $video->user->profile?->city,
                'completeness' => $video->user->profile?->completeness ?? 35,
            ],
            'counts' => ['views' => $video->views_count, 'likes' => $video->likes_count, 'comments' => $video->comments_count, 'shares' => $video->shares_count, 'saves' => $video->saves_count],
            'viewer' => [
                'liked' => (bool) $liked->has($video->id),
                'saved' => (bool) $saved->has($video->id),
                'reposted' => (bool) $reposted->has($video->id),
                'following_creator' => $followedIds->has($video->user_id),
                'is_owner' => $viewer?->id === $video->user_id,
                'can_manage' => $viewer?->id === $video->user_id,
            ],
            'sponsored' => $video->getAttribute('sponsored'),
        ])->values()->all();
    }
}
