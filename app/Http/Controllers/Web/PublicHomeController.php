<?php

namespace App\Http\Controllers\Web;

use App\Domain\Feed\Models\Video;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class PublicHomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if (! Schema::hasTable('videos')) {
            return $this->render(collect());
        }

        $videos = Video::query()
            ->where('status', 'published')->where('visibility', 'public')
            ->where(fn ($query) => $query
                ->whereHas('media', fn ($media) => $media->where('processing_status', 'ready')->where('moderation_status', 'approved'))
                ->orWhereHas('images', fn ($media) => $media->where('processing_status', 'ready')->where('moderation_status', 'approved')))
            ->with(['user.profile', 'user.athleteProfile.sport', 'user.athleteProfile.taxonomyPosition', 'sport', 'media', 'images'])
            ->orderByRaw('(views_count + (likes_count * 3) + (comments_count * 4) + (shares_count * 5)) DESC')
            ->latest('published_at')->limit(5)->get();

        return $this->render($videos);
    }

    private function render($videos): Response
    {
        return Inertia::render('Public/Home', [
            'highlights' => $videos->map(fn (Video $video) => $this->video($video))->values(),
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
