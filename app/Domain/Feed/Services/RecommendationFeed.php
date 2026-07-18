<?php

namespace App\Domain\Feed\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecommendationFeed
{
    public function ids(User $user): Collection
    {
        return Cache::remember($this->key($user->id), config('scale.feed_cache_seconds'), fn () =>
            DB::table('recommendation_feed_items')->where('user_id', $user->id)
                ->orderBy('position')->limit(config('scale.recommendation_size'))->pluck('video_id')
        );
    }

    public function rebuild(User $user): int
    {
        $limit = config('scale.recommendation_size');
        $generation = (string) Str::ulid();
        $candidates = DB::table('videos')
            ->where('videos.status', 'published')->where('videos.visibility', 'public')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('feed_preferences')
                ->where('feed_preferences.user_id', $user->id)->whereColumn('feed_preferences.video_id', 'videos.id'))
            ->select('videos.id')
            ->selectRaw('(likes_count * 3 + comments_count * 4 + shares_count * 5 + views_count * 0.05)
                + CASE WHEN EXISTS (SELECT 1 FROM follows WHERE follows.follower_id = ? AND follows.followed_id = videos.user_id) THEN 1000 ELSE 0 END AS recommendation_score', [$user->id])
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
    private function key(int $userId): string { return "feed:recommended:{$userId}"; }
}
