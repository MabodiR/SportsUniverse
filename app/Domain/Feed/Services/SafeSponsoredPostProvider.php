<?php

namespace App\Domain\Feed\Services;

use App\Contracts\Feed\SponsoredPostProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Throwable;

class SafeSponsoredPostProvider
{
    public function __construct(private SponsoredPostProvider $provider) {}

    public function insert(Collection $posts, Request $request): Collection
    {
        try {
            return $this->provider->insert($posts, $request);
        } catch (Throwable $exception) {
            report($exception);
            return $posts;
        }
    }
}
