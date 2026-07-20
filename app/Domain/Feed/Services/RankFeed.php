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

        app(PrioritizeUnseenPosts::class)->execute($query, $user);

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

        $score = sprintf('(videos.likes_count * %F + videos.comments_count * %F + videos.shares_count * %F + videos.views_count * %F)', $settings->like_weight, $settings->comment_weight, $settings->share_weight, $settings->view_weight);
        if ($user && $settings->ranking_mode === 'personalized') {
            $score .= sprintf(' + CASE WHEN EXISTS (SELECT 1 FROM follows WHERE follows.follower_id = %d AND follows.followed_id = videos.user_id) THEN %F ELSE 0 END', $user->id, $settings->follow_boost);
            $score .= sprintf(' + COALESCE((SELECT SUM(content_topics.weight * preferences.score) FROM video_content_topics content_topics JOIN user_content_preferences preferences ON preferences.dimension = content_topics.dimension AND preferences.value = content_topics.value AND preferences.user_id = %d WHERE content_topics.video_id = videos.id), 0)', $user->id);
        }

        return $query->selectRaw("{$score} AS feed_rank_score")->orderByDesc('feed_rank_score')->orderByDesc('videos.id');
    }
}
