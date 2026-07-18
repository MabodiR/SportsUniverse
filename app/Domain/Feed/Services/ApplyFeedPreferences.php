<?php

namespace App\Domain\Feed\Services;

use App\Domain\Feed\Models\FeedPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ApplyFeedPreferences
{
    public function execute(Builder $query, ?User $user): Builder
    {
        if (! $user) return $query;

        // Keep exclusions inside PostgreSQL. Materialising every preference into an
        // IN (...) list becomes expensive for long-lived, highly active accounts.
        $query->whereNotExists(fn ($preferences) => $preferences
            ->selectRaw('1')
            ->from('feed_preferences')
            ->where('feed_preferences.user_id', $user->id)
            ->whereColumn('feed_preferences.video_id', 'videos.id'));
        $query->whereNotExists(fn ($preferences) => $preferences
            ->selectRaw('1')
            ->from('feed_preferences')
            ->where('feed_preferences.user_id', $user->id)
            ->where('feed_preferences.scope', 'creator')
            ->whereColumn('feed_preferences.creator_id', 'videos.user_id'));
        $query->whereNotExists(fn ($preferences) => $preferences
            ->selectRaw('1')
            ->from('feed_preferences')
            ->where('feed_preferences.user_id', $user->id)
            ->where('feed_preferences.scope', 'sport')
            ->whereColumn('feed_preferences.sport_id', 'videos.sport_id'));

        // Similar-content feedback contains JSON metadata, so only retrieve this
        // small subset. Direct post/creator/sport exclusions remain fully indexed.
        $tags = FeedPreference::query()->where('user_id', $user->id)->where('scope', 'similar')
            ->pluck('metadata')->flatMap(fn ($metadata) => $metadata['hashtags'] ?? [])->filter()->unique();
        foreach ($tags as $tag) $query->whereJsonDoesntContain('hashtags', $tag);

        return $query;
    }
}
