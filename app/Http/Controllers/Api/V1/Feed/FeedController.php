<?php

namespace App\Http\Controllers\Api\V1\Feed;

use App\Domain\Feed\Models\Video;
use App\Domain\Feed\Services\ApplyFeedPreferences;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Feed\VideoResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class FeedController extends Controller
{
    public function forYou(Request $request, VideoController $videos): AnonymousResourceCollection
    {
        $query = app(ApplyFeedPreferences::class)->execute($this->published(), $request->user());
        $fanSports = $request->user()->hasRole('fan') ? collect($request->user()->fanProfile?->interested_sports ?? [])->map(fn ($sport) => mb_strtolower((string) $sport))->filter()->values()->all() : [];
        if ($fanSports) {
            $query->where(fn ($videos) => $videos
                ->whereHas('sport', fn ($sport) => $sport->whereIn(DB::raw('LOWER(name)'), $fanSports))
                ->orWhereHas('user.athleteProfile.sport', fn ($sport) => $sport->whereIn(DB::raw('LOWER(name)'), $fanSports)));
        }
        if ($request->filled('sport')) {
            $query->whereHas('sport', fn ($q) => $q->where('slug', $request->string('sport')));
        }$page = $query->orderByRaw('(likes_count * 3 + comments_count * 4 + shares_count * 5 + views_count * 0.05) DESC')->latest('published_at')->cursorPaginate(15);
        $videos->decorate($page->getCollection(), $request);

        return VideoResource::collection($page);
    }

    public function following(Request $request, VideoController $videos): AnonymousResourceCollection
    {
        $page = app(ApplyFeedPreferences::class)->execute($this->published(), $request->user())->whereIn('user_id', $request->user()->following()->select('users.id'))->latest('published_at')->cursorPaginate(15);
        $videos->decorate($page->getCollection(), $request);

        return VideoResource::collection($page);
    }

    private function published()
    {
        return Video::query()->where('status', 'published')->where('visibility', 'public')->where(function($post){$post->whereHas('media', fn ($q) => $q->where('processing_status', 'ready')->where('moderation_status', 'approved'))->orWhereHas('images', fn ($q) => $q->where('processing_status', 'ready')->where('moderation_status', 'approved'));})->with('user.profile','media','images','sport');
    }
}
