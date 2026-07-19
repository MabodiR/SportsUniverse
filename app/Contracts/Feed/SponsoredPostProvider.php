<?php

namespace App\Contracts\Feed;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface SponsoredPostProvider
{
    public function insert(Collection $posts, Request $request): Collection;
}
