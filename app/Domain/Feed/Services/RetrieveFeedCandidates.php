<?php

namespace App\Domain\Feed\Services;

use App\Domain\Feed\Models\Video;
use Illuminate\Database\Eloquent\Builder;

class RetrieveFeedCandidates
{
    public function execute(Builder $query, ?int $limit = null): Builder
    {
        $limit ??= (int) config('scale.feed_candidate_size', 5_000);
        $candidateIds = Video::query()->select('videos.id')
            ->where('videos.status', 'published')->where('videos.visibility', 'public')
            ->orderByDesc('videos.published_at')->orderByDesc('videos.id')->limit($limit);

        return $query->whereIn('videos.id', $candidateIds);
    }
}
