<?php

namespace App\Console\Commands;

use App\Domain\Feed\Jobs\BuildRecommendationFeed;
use App\Models\User;
use Illuminate\Console\Command;

class BuildRecommendationFeeds extends Command
{
    protected $signature = 'feed:precompute {--user=} {--sync}';
    protected $description = 'Precompute ranked recommendation feeds for active users';
    public function handle(): int
    {
        $activeSince = now()->subDays(config('scale.recommendation_active_days'))->timestamp;
        User::query()->when($this->option('user'), fn ($q, $id) => $q->whereKey($id), fn ($q) => $q
                ->where(fn ($active) => $active
                    ->whereExists(fn ($sessions) => $sessions->selectRaw('1')->from('sessions')->whereColumn('sessions.user_id', 'users.id')->where('sessions.last_activity', '>=', $activeSince))
                    ->orWhereExists(fn ($tokens) => $tokens->selectRaw('1')->from('personal_access_tokens')->whereColumn('personal_access_tokens.tokenable_id', 'users.id')->where('personal_access_tokens.tokenable_type', User::class)->where('personal_access_tokens.last_used_at', '>=', now()->subDays(config('scale.recommendation_active_days'))))))
            ->where('status', 'active')->orderBy('id')->chunkById(500, function ($users) {
                foreach ($users as $user) $this->option('sync')
                    ? BuildRecommendationFeed::dispatchSync($user->id)
                    : BuildRecommendationFeed::dispatch($user->id);
            });
        $this->info('Recommendation feed builds scheduled.');
        return self::SUCCESS;
    }
}
