<?php

namespace App\Http\Controllers\Api\V1\Feed;

use App\Domain\Feed\Actions\ToggleVideoEngagement;
use App\Domain\Feed\Models\Comment;
use App\Domain\Feed\Models\Video;
use App\Domain\Feed\Services\BufferedVideoCounters;
use App\Events\NotificationRequested;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Feed\RecordViewRequest;
use App\Http\Requests\Api\V1\Feed\StoreCommentRequest;
use App\Http\Resources\Api\V1\Feed\CommentResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EngagementController extends Controller
{
    public function like(Request $request, Video $video, ToggleVideoEngagement $toggle): JsonResponse
    {
        Gate::authorize('view', $video);
        $active = $toggle->execute($request->user(), $video, 'like');
        if ($active && $video->user_id !== $request->user()->id) {
            NotificationRequested::dispatch($video->user_id, 'engagement', ['event' => 'video_liked', 'actor_id' => $request->user()->id, 'actor_name' => $request->user()->name, 'video_id' => $video->public_id]);
        }

        return response()->json(['data' => ['liked' => $active, 'likes_count' => $video->fresh()->likes_count]]);
    }

    public function save(Request $request, Video $video, ToggleVideoEngagement $toggle): JsonResponse
    {
        Gate::authorize('view', $video);
        $active = $toggle->execute($request->user(), $video, 'save');

        return response()->json(['data' => ['saved' => $active, 'saves_count' => $video->fresh()->saves_count]]);
    }

    public function comment(StoreCommentRequest $request, Video $video): JsonResponse
    {
        Gate::authorize('view', $video);
        abort_unless($video->comments_enabled, 403, 'Comments are disabled for this post.');
        $parent = null;
        if ($request->filled('parent_id')) {
            $parent = Comment::where('public_id', $request->validated('parent_id'))->where('video_id', $video->id)->first();
            if (! $parent) {
                throw ValidationException::withMessages(['parent_id' => ['The parent comment does not belong to this video.']]);
            }
        }$comment = DB::transaction(function () use ($request, $video, $parent) {
            $comment = $video->comments()->create(['public_id' => (string) Str::ulid(), 'user_id' => $request->user()->id, 'parent_id' => $parent?->id, 'body' => $request->validated('body')]);
            $video->increment('comments_count');

            return $comment;
        });
        if ($video->user_id !== $request->user()->id) {
            NotificationRequested::dispatch($video->user_id, 'engagement', ['event' => 'video_commented', 'actor_id' => $request->user()->id, 'actor_name' => $request->user()->name, 'video_id' => $video->public_id, 'comment_id' => $comment->public_id]);
        }

        return response()->json(['message' => 'Comment added.', 'data' => new CommentResource($comment->load('user.profile', 'parent'))], 201);
    }

    public function share(Request $request, Video $video): JsonResponse
    {
        Gate::authorize('view', $video);
        $channel = $request->validate(['channel' => ['nullable', 'string', 'in:copy_link,whatsapp,facebook,x,email,repost,other']])['channel'] ?? 'copy_link';
        $reposted = null;
        DB::transaction(function () use ($request, $video, $channel, &$reposted) {
            $lockedVideo = Video::whereKey($video->id)->lockForUpdate()->firstOrFail();
            if ($channel === 'repost') {
                $existing = DB::table('video_shares')->where(['video_id' => $video->id, 'user_id' => $request->user()->id, 'channel' => 'repost'])->lockForUpdate()->first();
                if ($existing) {
                    DB::table('video_shares')->where('id', $existing->id)->delete();
                    $lockedVideo->update(['shares_count' => max(0, $lockedVideo->shares_count - 1)]);
                    $reposted = false;
                    return;
                }
                $reposted = true;
            }
            DB::table('video_shares')->insert(['video_id' => $video->id, 'user_id' => $request->user()->id, 'channel' => $channel, 'created_at' => now(), 'updated_at' => now()]);
            $lockedVideo->increment('shares_count');
        });

        return response()->json(['data' => ['shares_count' => $video->fresh()->shares_count, 'reposted' => $reposted]]);
    }

    public function view(RecordViewRequest $request, Video $video, BufferedVideoCounters $counters): JsonResponse
    {
        Gate::authorize('view', $video);
        $created = false;
        DB::transaction(function () use ($request, $video, &$created) {
            $date = today()->toDateString();
            $table = DB::getDriverName() === 'pgsql' ? config('scale.video_views_table') : 'video_views';
            $query = DB::table($table)->where(['video_id' => $video->id, 'user_id' => $request->user()->id, 'viewed_on' => $date]);
            $record = $query->lockForUpdate()->first();
            if ($record) {
                $query->update([
                    'watched_ms' => max((int) $record->watched_ms, $request->integer('watched_ms')),
                    'completed' => (bool) $record->completed || $request->boolean('completed'),
                    'updated_at' => now(),
                ]);

                return;
            }
            DB::table($table)->insert(['video_id' => $video->id, 'user_id' => $request->user()->id, 'watched_ms' => $request->integer('watched_ms'), 'completed' => $request->boolean('completed'), 'viewed_on' => $date, 'created_at' => now(), 'updated_at' => now()]);
            $created = true;
        });

        $views = $created ? $counters->increment($video) : (int) $video->fresh()->views_count + $counters->pending($video->id);

        return response()->json(['data' => ['counted' => $created, 'views_count' => $views]]);
    }

    public function follow(Request $request, User $user): JsonResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot follow yourself.');
        $attached = $request->user()->following()->syncWithoutDetaching([$user->id]);
        if ($attached['attached'] !== []) {
            NotificationRequested::dispatch($user->id, 'followers', ['event' => 'new_follower', 'actor_id' => $request->user()->id, 'actor_name' => $request->user()->name]);
        }

        return response()->json(['data' => [
            'following' => true,
            'created' => $attached['attached'] !== [],
            'followers_count' => $user->followers()->count(),
            'viewer_following_count' => $request->user()->following()->count(),
        ]]);
    }

    public function unfollow(Request $request, User $user): JsonResponse
    {
        $request->user()->following()->detach($user);

        return response()->json(['data' => [
            'following' => false,
            'followers_count' => $user->followers()->count(),
            'viewer_following_count' => $request->user()->following()->count(),
        ]]);
    }

    public function likeComment(Request $request, Comment $comment): JsonResponse
    {
        Gate::authorize('view', $comment->video);
        $existing = $comment->likers()->whereKey($request->user()->id)->exists();
        $existing ? $comment->likers()->detach($request->user()) : $comment->likers()->attach($request->user(), ['created_at' => now()]);
        $count = $comment->likers()->count();
        $comment->update(['likes_count' => $count]);

        return response()->json(['data' => ['liked' => ! $existing, 'likes_count' => $count]]);
    }
}
