<?php

namespace App\Http\Controllers\Api\V1\Feed;

use App\Domain\Feed\Models\Video;
use App\Domain\Feed\Models\FeedSetting;
use App\Domain\Feed\Services\SafeSponsoredPostProvider;
use App\Domain\Feed\Services\ApplyFeedPreferences;
use App\Domain\Feed\Services\RankFeed;
use App\Domain\Feed\Services\RecommendationFeed;
use App\Domain\Feed\Services\DiversifyFeed;
use App\Domain\Feed\Services\RetrieveFeedCandidates;
use App\Domain\Advertising\Services\SponsoredFeedDelivery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Feed\VideoResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class FeedController extends Controller
{
    public function stories(Request $request, VideoController $videos, SponsoredFeedDelivery $sponsored): AnonymousResourceCollection
    {
        $user = $request->user();
        $organic = Video::query()->where('post_type', 'story')->where('status', 'published')->where('expires_at', '>', now())
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere(fn ($followed) => $followed->where('visibility', 'followers')->whereIn('user_id', $user->following()->select('users.id'))))
            ->with('user.profile', 'media', 'images', 'sport')->latest('published_at')->limit(100)->get();
        $promoted = $sponsored->stories($request);
        $stories = $organic->concat($promoted)->unique(fn ($story) => $story->id.':'.($story->sponsored['delivery_id'] ?? 'organic'))->values();
        $videos->decorate($stories, $request);

        return VideoResource::collection($stories);
    }

    public function forYou(Request $request, VideoController $videos, RecommendationFeed $recommendations): AnonymousResourceCollection
    {
        $query = app(ApplyFeedPreferences::class)->execute($this->published(false), $request->user());
        $precomputed = $recommendations->ids($request->user());
        $settings = FeedSetting::current();
        $fanSports = $settings->use_fan_sports && $request->user()->hasRole('fan') ? collect($request->user()->fanProfile?->interested_sports ?? [])->map(fn ($sport) => mb_strtolower((string) $sport))->filter()->values()->all() : [];
        if ($fanSports) {
            $query->where(fn ($videos) => $videos
                ->whereHas('sport', fn ($sport) => $sport->whereIn(DB::raw('LOWER(name)'), $fanSports))
                ->orWhereHas('user.athleteProfile.sport', fn ($sport) => $sport->whereIn(DB::raw('LOWER(name)'), $fanSports)));
        }
        if ($request->filled('sport')) {
            $query->whereHas('sport', fn ($q) => $q->where('slug', $request->string('sport')));
        }
        if ($precomputed->isEmpty()) app(RetrieveFeedCandidates::class)->execute($query);
        app(RankFeed::class)->execute($query, $request->user(), $precomputed);
        $page = $query->cursorPaginate($settings->page_size);
        $page->setCollection(app(DiversifyFeed::class)->execute($page->getCollection()));
        $page->setCollection(app(SafeSponsoredPostProvider::class)->insert($page->getCollection(), $request));
        $videos->decorate($page->getCollection(), $request);

        return VideoResource::collection($page);
    }

    public function following(Request $request, VideoController $videos): AnonymousResourceCollection
    {
        $page = app(ApplyFeedPreferences::class)->execute($this->published(true), $request->user())
            ->where(fn ($videos) => $videos
                ->where('videos.user_id', $request->user()->id)
                ->orWhereExists(fn ($follows) => $follows->selectRaw('1')->from('follows')
                    ->where('follows.follower_id', $request->user()->id)
                    ->whereColumn('follows.followed_id', 'videos.user_id')))
            ->orderByDesc('published_at')->orderByDesc('id')->cursorPaginate(15);
        $videos->decorate($page->getCollection(), $request);

        return VideoResource::collection($page);
    }

    private function published(bool $includeStories)
    {
        return Video::query()->select('videos.*')->where('status', 'published')
            ->when($includeStories,
                fn ($query) => $query->whereIn('visibility', ['public', 'followers']),
                fn ($query) => $query->where('visibility', 'public')->where('post_type', 'post'))
            ->where(fn ($query) => $query->where('post_type', 'post')->orWhere('expires_at', '>', now()))
            ->where(function($post){$post->whereHas('media', fn ($q) => $q->where('processing_status', 'ready')->where('moderation_status', 'approved'))->orWhereHas('images', fn ($q) => $q->where('processing_status', 'ready')->where('moderation_status', 'approved'));})->with('user.profile','media','images','sport');
    }
}
