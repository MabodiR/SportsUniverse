<?php

namespace App\Domain\Feed\Services;

use App\Domain\Feed\Models\Video;
use Illuminate\Support\Collection;

class SimilarVideos
{
    public function find(Video $video, int $limit = 12): Collection
    {
        $source = $video->content_embedding ?? [];
        if (! $source) return collect();
        return Video::query()->whereKeyNot($video->id)->where('status', 'published')->where('visibility', 'public')
            ->whereNotNull('content_embedding')->when($video->sport_id, fn ($query) => $query->where('sport_id', $video->sport_id))
            ->with('user.profile', 'media', 'images', 'sport')->latest('published_at')->limit(250)->get()
            ->map(fn ($candidate) => ['video' => $candidate, 'similarity' => $this->cosine($source, $candidate->content_embedding ?? [])])
            ->sortByDesc('similarity')->take($limit)->pluck('video')->values();
    }

    private function cosine(array $left, array $right): float
    {
        if (count($left) !== count($right) || ! $left) return 0;
        $dot = $a = $b = 0.0;
        foreach ($left as $index => $value) { $dot += $value * $right[$index]; $a += $value ** 2; $b += $right[$index] ** 2; }
        return $a && $b ? $dot / (sqrt($a) * sqrt($b)) : 0;
    }
}
