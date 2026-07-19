<?php

namespace App\Domain\Feed\Services;

use App\Domain\Feed\Models\FeedSetting;
use App\Domain\Feed\Models\Video;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecommendationFeed
{
    public function ids(User $user): Collection
    {
        $size = FeedSetting::current()->recommendation_size;
        $key = $this->key($user->id);
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return collect($cached);
        }

        // Older releases cached a Collection. With cache class unserialization
        // disabled, those entries are restored as __PHP_Incomplete_Class.
        if ($cached instanceof Collection) {
            $ids = $cached->values()->all();
            Cache::put($key, $ids, config('scale.feed_cache_seconds'));

            return collect($ids);
        }

        if ($cached !== null) {
            Cache::forget($key);
        }

        $ids = DB::table('recommendation_feed_items')->where('user_id', $user->id)
            ->orderBy('position')->limit($size)->pluck('video_id')->all();
        Cache::put($key, $ids, config('scale.feed_cache_seconds'));

        return collect($ids);
    }

    public function rebuild(User $user): int
    {
        $settings = FeedSetting::current();
        $limit = $settings->recommendation_size;
        $generation = (string) Str::ulid();
        $score = '(likes_count * CAST(? AS DECIMAL(12,4)) + comments_count * CAST(? AS DECIMAL(12,4)) + shares_count * CAST(? AS DECIMAL(12,4)) + views_count * CAST(? AS DECIMAL(12,4)))';
        $scoreBindings = [$settings->like_weight, $settings->comment_weight, $settings->share_weight, $settings->view_weight];
        if ($settings->ranking_mode === 'personalized') {
            $score .= ' + CASE WHEN EXISTS (SELECT 1 FROM follows WHERE follows.follower_id = ? AND follows.followed_id = videos.user_id) THEN CAST(? AS DECIMAL(12,4)) ELSE 0 END';
            array_push($scoreBindings, $user->id, $settings->follow_boost);
        }

        $candidates = app(ApplyFeedPreferences::class)->execute(Video::query(), $user)
            ->where('videos.status', 'published')->where('videos.visibility', 'public')
            ->where(fn ($post) => $post
                ->whereHas('media', fn ($media) => $media->where('processing_status', 'ready')->where('moderation_status', 'approved'))
                ->orWhereHas('images', fn ($media) => $media->where('processing_status', 'ready')->where('moderation_status', 'approved')))
            ->select('videos.id')
            ->selectRaw("{$score} AS recommendation_score", $scoreBindings)
            ->orderByDesc('recommendation_score')->orderByDesc('published_at')->orderByDesc('videos.id')
            ->limit($limit)->get();

        $now = now();
        $rows = $candidates->values()->map(fn ($item, $position) => [
            'user_id' => $user->id, 'video_id' => $item->id, 'score' => $item->recommendation_score,
            'position' => $position, 'generation' => $generation, 'generated_at' => $now,
        ]);
        DB::transaction(function () use ($user, $rows) {
            DB::table('recommendation_feed_items')->where('user_id', $user->id)->delete();
            foreach ($rows->chunk(500) as $chunk) DB::table('recommendation_feed_items')->insert($chunk->all());
        });
        Cache::forget($this->key($user->id));

        return $candidates->count();
    }

    public function invalidate(int $userId): void { Cache::forget($this->key($userId)); }
    private function key(int $userId): string
    {
        $version = FeedSetting::current()->updated_at?->getTimestamp() ?? 0;
        return "feed:recommended:{$version}:{$userId}";
    }
}
