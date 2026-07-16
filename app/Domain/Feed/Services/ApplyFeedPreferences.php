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
        $preferences = FeedPreference::where('user_id', $user->id)->get();
        $query->whereNotIn('id', $preferences->pluck('video_id')->filter());
        $query->whereNotIn('user_id', $preferences->where('scope', 'creator')->pluck('creator_id')->filter());
        $sportIds = $preferences->where('scope', 'sport')->pluck('sport_id')->filter()->unique();
        if ($sportIds->isNotEmpty()) {
            $query->where(fn ($videos) => $videos->whereNull('sport_id')->orWhereNotIn('sport_id', $sportIds));
        }
        $tags = $preferences->where('scope', 'similar')->flatMap(fn ($preference) => $preference->metadata['hashtags'] ?? [])->filter()->unique();
        foreach ($tags as $tag) $query->whereJsonDoesntContain('hashtags', $tag);

        return $query;
    }
}
