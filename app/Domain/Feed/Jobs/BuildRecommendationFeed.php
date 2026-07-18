<?php

namespace App\Domain\Feed\Jobs;

use App\Domain\Feed\Services\RecommendationFeed;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildRecommendationFeed implements ShouldQueue
{
    use Queueable;
    public int $tries = 3;
    public int $timeout = 300;
    public function __construct(public int $userId) { $this->onQueue('feeds'); }
    public function handle(RecommendationFeed $feeds): void
    {
        $user = User::find($this->userId);
        if ($user) $feeds->rebuild($user);
    }
}
