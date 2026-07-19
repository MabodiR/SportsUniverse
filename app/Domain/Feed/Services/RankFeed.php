<?php

namespace App\Domain\Feed\Services;

use App\Domain\Feed\Models\FeedSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RankFeed
{
    public function execute(Builder $query, ?User $user, Collection $precomputed): Builder
    {
        $settings = FeedSetting::current();

        if ($settings->ranking_mode === 'recent') {
            return $query->orderByDesc('videos.published_at')->orderByDesc('videos.id');
        }

        if ($user && $settings->ranking_mode === 'personalized' && $precomputed->isNotEmpty()) {
            return $query->join('recommendation_feed_items as recommended', fn ($join) => $join
                ->on('recommended.video_id', '=', 'videos.id')
                ->where('recommended.user_id', $user->id))
                ->addSelect('recommended.position as recommendation_position')
                ->orderBy('recommendation_position')->orderByDesc('videos.id');
        }

        $score = '(videos.likes_count * CAST(? AS DECIMAL(12,4)) + videos.comments_count * CAST(? AS DECIMAL(12,4)) + videos.shares_count * CAST(? AS DECIMAL(12,4)) + videos.views_count * CAST(? AS DECIMAL(12,4)))';
        $bindings = [$settings->like_weight, $settings->comment_weight, $settings->share_weight, $settings->view_weight];
        if ($user && $settings->ranking_mode === 'personalized') {
            $score .= ' + CASE WHEN EXISTS (SELECT 1 FROM follows WHERE follows.follower_id = ? AND follows.followed_id = videos.user_id) THEN CAST(? AS DECIMAL(12,4)) ELSE 0 END';
            array_push($bindings, $user->id, $settings->follow_boost);
        }

        return $query->orderByRaw("{$score} DESC", $bindings)
            ->orderByDesc('videos.published_at')->orderByDesc('videos.id');
    }
}
