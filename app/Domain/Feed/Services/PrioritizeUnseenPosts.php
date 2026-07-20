<?php

namespace App\Domain\Feed\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PrioritizeUnseenPosts
{
    public function execute(Builder $query, ?User $user, int $cooldownDays = 3): Builder
    {
        if (! $user) {
            return $query;
        }

        $viewsTable = DB::getDriverName() === 'pgsql'
            ? config('scale.video_views_table')
            : 'video_views';

        $since = now()->subDays($cooldownDays)->toDateString();
        return $query->selectRaw("CASE WHEN EXISTS (SELECT 1 FROM {$viewsTable} recent_views WHERE recent_views.user_id = {$user->id} AND recent_views.video_id = videos.id AND recent_views.viewed_on >= '{$since}') THEN 1 ELSE 0 END AS recently_viewed")->orderBy('recently_viewed');
    }
}
