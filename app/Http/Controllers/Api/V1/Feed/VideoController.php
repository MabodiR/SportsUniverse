<?php

namespace App\Http\Controllers\Api\V1\Feed;

use App\Domain\Feed\Models\Video;
use App\Domain\Media\Models\Media;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Feed\StoreVideoRequest;
use App\Http\Requests\Api\V1\Feed\UpdateVideoRequest;
use App\Http\Resources\Api\V1\Feed\CommentResource;
use App\Http\Resources\Api\V1\Feed\VideoResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VideoController extends Controller
{
    public function mine(Request $request): AnonymousResourceCollection
    {
        $videos = Video::where('user_id', $request->user()->id)->where('status', 'published')->latest('published_at')->with('user.profile', 'media', 'images', 'sport')->paginate(50);
        $this->decorate($videos->getCollection(), $request);

        return VideoResource::collection($videos);
    }

    public function reposts(Request $request): AnonymousResourceCollection
    {
        return $this->engagementCollection($request, Video::whereIn('id', DB::table('video_shares')->select('video_id')->where('user_id', $request->user()->id)->where('channel', 'repost')));
    }

    public function favourites(Request $request): AnonymousResourceCollection
    {
        return $this->engagementCollection($request, Video::whereHas('savers', fn ($query) => $query->whereKey($request->user()->id)));
    }

    public function liked(Request $request): AnonymousResourceCollection
    {
        return $this->engagementCollection($request, Video::whereHas('likers', fn ($query) => $query->whereKey($request->user()->id)));
    }

    private function engagementCollection(Request $request, $query): AnonymousResourceCollection
    {
        $videos = $query->where('status', 'published')->where('visibility', 'public')->latest('published_at')->with('user.profile', 'media', 'images', 'sport')->paginate(50);
        $this->decorate($videos->getCollection(), $request);

        return VideoResource::collection($videos);
    }

    public function store(StoreVideoRequest $request): JsonResponse
    {
        $media = $request->filled('media_id') ? Media::where('public_id', $request->validated('media_id'))->where('user_id', $request->user()->id)->first() : null;
        if ($request->filled('media_id') && (! $media || $media->kind !== 'video' || $media->processing_status !== 'ready' || $media->moderation_status !== 'approved')) {
            throw ValidationException::withMessages(['media_id' => ['Use an owned, approved video that has finished processing.']]);
        }
        $imageIds = $request->validated('image_media_ids', []);
        $images = Media::whereIn('public_id', $imageIds)->where('user_id', $request->user()->id)->get();
        if ($images->count() !== count($imageIds) || $images->contains(fn (Media $image) => $image->kind !== 'image' || $image->processing_status !== 'ready' || $image->moderation_status !== 'approved')) {
            throw ValidationException::withMessages(['image_media_ids' => ['Use owned, approved images that have finished processing.']]);
        }
        $publish = $request->boolean('publish');
        $video = DB::transaction(function () use ($request, $media, $images, $publish) {
            $video = Video::create(['public_id' => (string) Str::ulid(), 'user_id' => $request->user()->id, 'media_id' => $media?->id, 'sport_id' => $request->validated('sport_id'), 'caption' => $request->validated('caption'), 'hashtags' => $this->hashtags($request->validated('hashtags', [])), 'location_name' => $request->validated('location_name'), 'latitude' => $request->validated('latitude'), 'longitude' => $request->validated('longitude'), 'comments_enabled' => $request->boolean('comments_enabled', true), 'visibility' => $request->validated('visibility', 'public'), 'status' => $publish ? 'published' : 'draft', 'published_at' => $publish ? now() : null]);
            $coverId = $request->validated('cover_media_id') ?? $images->first()?->public_id;
            $video->images()->attach($images->values()->mapWithKeys(fn (Media $image, int $position) => [$image->id => ['position' => $position, 'is_cover' => $image->public_id === $coverId]])->all());
            return $video;
        });

        return response()->json(['message' => $publish ? 'Video published.' : 'Draft created.', 'data' => new VideoResource($this->load($video, $request))], 201);
    }

    public function update(UpdateVideoRequest $request, Video $video): VideoResource
    {
        Gate::authorize('update', $video);
        $video->update([
            'sport_id' => $request->validated('sport_id', $video->sport_id),
            'caption' => $request->validated('caption', $video->caption),
            'hashtags' => $request->has('hashtags') ? $this->hashtags($request->validated('hashtags', [])) : $video->hashtags,
            'location_name' => $request->validated('location_name', $video->location_name),
            'latitude' => $request->validated('latitude', $video->latitude),
            'longitude' => $request->validated('longitude', $video->longitude),
            'comments_enabled' => $request->has('comments_enabled') ? $request->boolean('comments_enabled') : $video->comments_enabled,
            'visibility' => $request->validated('visibility', $video->visibility),
        ]);
        if ($request->filled('cover_media_id')) {
            $cover = Media::where('public_id', $request->validated('cover_media_id'))->where('user_id', $request->user()->id)->firstOrFail();
            abort_unless($video->images()->whereKey($cover->id)->exists(), 422);
            DB::table('video_images')->where('video_id', $video->id)->update(['is_cover' => false]);
            DB::table('video_images')->where('video_id', $video->id)->where('media_id', $cover->id)->update(['is_cover' => true]);
        }
        return new VideoResource($this->load($video, $request));
    }

    private function hashtags(array $hashtags): array
    {
        return collect($hashtags)->map(fn ($tag) => str($tag)->trim()->ltrim('#')->lower()->value())->filter()->unique()->values()->all();
    }

    public function show(Request $request, Video $video): VideoResource
    {
        Gate::authorize('view', $video);

        return new VideoResource($this->load($video, $request));
    }

    public function destroy(Video $video): JsonResponse
    {
        Gate::authorize('delete', $video);
        $video->delete();

        return response()->json(['message' => 'Video deleted.']);
    }

    public function comments(Video $video): AnonymousResourceCollection
    {
        Gate::authorize('view', $video);

        $comments = $video->comments()->whereNull('parent_id')->where('moderation_status', 'approved')->with('user.profile', 'parent', 'replies.user.profile')->latest()->paginate(30);
        if (request()->user()) {
            $ids = $comments->getCollection()->flatMap(fn ($comment) => collect([$comment])->merge($comment->replies))->pluck('id');
            $liked = DB::table('comment_likes')->where('user_id', request()->user()->id)->whereIn('comment_id', $ids)->pluck('comment_id')->flip();
            $comments->getCollection()->each(function ($comment) use ($liked) {
                $comment->liked_by_viewer = $liked->has($comment->id);
                $comment->replies->each(fn ($reply) => $reply->liked_by_viewer = $liked->has($reply->id));
            });
        }

        return CommentResource::collection($comments);
    }

    public function saved(Request $request): AnonymousResourceCollection
    {
        $videos = Video::query()->whereHas('savers', fn ($q) => $q->whereKey($request->user()->id))->where('status', 'published')->latest('published_at')->with('user.profile', 'media', 'images', 'sport')->paginate(20);
        $this->decorate($videos->getCollection(), $request);

        return VideoResource::collection($videos);
    }

    private function load(Video $video, Request $request): Video
    {
        $video->load('user.profile', 'media', 'images', 'sport');
        $this->decorate(collect([$video]), $request);

        return $video;
    }

    public function decorate($videos, Request $request): void
    {
        $user = $request->user();
        if (! $user) {
            return;
        }$ids = $videos->pluck('id');
        $liked = $user->id ? \DB::table('video_likes')->where('user_id', $user->id)->whereIn('video_id', $ids)->pluck('video_id')->flip() : collect();
        $saved = \DB::table('saved_videos')->where('user_id', $user->id)->whereIn('video_id', $ids)->pluck('video_id')->flip();
        $following = $user->following()->pluck('users.id')->flip();
        foreach ($videos as $video) {
            $video->liked_by_viewer_exists = $liked->has($video->id);
            $video->saved_by_viewer_exists = $saved->has($video->id);
            $video->creator_followed_by_viewer_exists = $following->has($video->user_id);
        }
    }
}
