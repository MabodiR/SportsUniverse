<?php

namespace App\Domain\Feed\Services;

use Illuminate\Support\Collection;

class DiversifyFeed
{
    public function execute(Collection $videos): Collection
    {
        $remaining = $videos->values();
        $result = collect();
        while ($remaining->isNotEmpty()) {
            $recentCreators = $result->take(-4)->countBy('user_id');
            $index = $remaining->search(fn ($video) => ($recentCreators[$video->user_id] ?? 0) < 2);
            $index = $index === false ? 0 : $index;
            $result->push($remaining->pull($index));
            $remaining = $remaining->values();
        }

        return $result;
    }
}
