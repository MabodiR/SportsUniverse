<?php

namespace App\Http\Controllers\Api\V1\Feed;

use App\Domain\Feed\Models\Video;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Feed\VideoResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FeedController extends Controller
{
    public function forYou(Request $request, VideoController $videos): AnonymousResourceCollection
    {
        $query = $this->published();
        if ($request->filled('sport')) {
            $query->whereHas('sport', fn ($q) => $q->where('slug', $request->string('sport')));
        }$page = $query->orderByRaw('(likes_count * 3 + comments_count * 4 + shares_count * 5 + views_count * 0.05) DESC')->latest('published_at')->cursorPaginate(15);
        $videos->decorate($page->getCollection(), $request);

        return VideoResource::collection($page);
    }

    public function following(Request $request, VideoController $videos): AnonymousResourceCollection
    {
        $page = $this->published()->whereIn('user_id', $request->user()->following()->select('users.id'))->latest('published_at')->cursorPaginate(15);
        $videos->decorate($page->getCollection(), $request);

        return VideoResource::collection($page);
    }

    private function published()
    {
        return Video::query()->where('status', 'published')->where('visibility', 'public')->whereHas('media', fn ($q) => $q->where('processing_status', 'ready')->where('moderation_status', 'approved'))->with('user.profile','media','sport');
    }
}
