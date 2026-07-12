<?php

namespace App\Domain\Analytics\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AggregateDailyMetrics implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?string $date = null)
    {
        $this->onQueue('analytics');
    }

    public function handle(): void
    {
        $date = Carbon::parse($this->date ?? today())->toDateString();
        $metrics = ['profile_views' => $this->group('profile_views', 'profile_user_id', $date), 'video_views' => $this->joined('video_views', 'videos', 'video_views.video_id', 'videos.id', 'videos.user_id', $date), 'video_likes' => $this->joined('video_likes', 'videos', 'video_likes.video_id', 'videos.id', 'videos.user_id', $date), 'video_comments' => $this->joined('comments', 'videos', 'comments.video_id', 'videos.id', 'videos.user_id', $date), 'video_shares' => $this->joined('video_shares', 'videos', 'video_shares.video_id', 'videos.id', 'videos.user_id', $date), 'new_followers' => $this->group('follows', 'followed_id', $date), 'opportunity_applications' => $this->joined('opportunity_applications', 'opportunities', 'opportunity_applications.opportunity_id', 'opportunities.id', 'opportunities.posted_by_id', $date)];
        $rows = [];
        $now = now();
        foreach ($metrics as $metric => $values) {
            foreach ($values as $dimensionId => $value) {
                $rows[] = ['dimension_type' => 'user', 'dimension_id' => $dimensionId, 'metric' => $metric, 'metric_date' => $date, 'value' => $value, 'created_at' => $now, 'updated_at' => $now];
            }
        }$rows[] = ['dimension_type' => 'platform', 'dimension_id' => 0, 'metric' => 'new_users', 'metric_date' => $date, 'value' => User::whereDate('created_at', $date)->count(), 'created_at' => $now, 'updated_at' => $now];
        foreach ($metrics as $metric => $values) {
            $rows[] = ['dimension_type' => 'platform', 'dimension_id' => 0, 'metric' => $metric, 'metric_date' => $date, 'value' => array_sum($values), 'created_at' => $now, 'updated_at' => $now];
        }DB::table('analytics_daily_metrics')->upsert($rows, ['dimension_type', 'dimension_id', 'metric', 'metric_date'], ['value', 'updated_at']);
    }

    private function group(string $table, string $dimension, string $date): array
    {
        return DB::table($table)->whereDate('created_at', $date)->select($dimension, DB::raw('COUNT(*) as aggregate'))->groupBy($dimension)->pluck('aggregate', $dimension)->map(fn ($value) => (int) $value)->all();
    }

    private function joined(string $table, string $join, string $left, string $right, string $dimension, string $date): array
    {
        return DB::table($table)->join($join, $left, '=', $right)->whereDate($table.'.created_at', $date)->select($dimension,DB::raw('COUNT(*) as aggregate'))->groupBy($dimension)->pluck('aggregate',$dimension)->map(fn ($value) => (int) $value)->all();
    }
}
