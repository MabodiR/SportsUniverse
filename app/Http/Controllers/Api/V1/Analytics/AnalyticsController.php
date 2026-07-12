<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Domain\Analytics\Actions\RecordProfileView;
use App\Domain\Analytics\Models\DailyMetric;
use App\Domain\Feed\Models\Video;
use App\Domain\Opportunities\Models\OpportunityApplication;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AnalyticsController extends Controller
{
    public function recordProfileView(Request $request, string $slug, RecordProfileView $record): JsonResponse
    {
        $profile = User::whereHas('profile', fn ($q) => $q->where('slug', $slug)->where('is_public', true))->firstOrFail();
        $counted = $record->execute($request->user(), $profile, $request->string('source', 'profile')->value());

        return response()->json(['data' => ['counted' => $counted]]);
    }

    public function creator(Request $request): JsonResponse
    {
        $days = $this->days($request);
        $user = $request->user();
        $key = "analytics:user:{$user->id}:{$days}";
        $data = Cache::remember($key, 300, function () use ($user, $days) {
            $from = today()->subDays($days - 1);
            $daily = DailyMetric::where('dimension_type', 'user')->where('dimension_id', $user->id)->whereDate('metric_date', '>=', $from)->orderBy('metric_date')->get()->groupBy('metric')->map(fn ($items) => $items->map(fn ($item) => ['date' => $item->metric_date->toDateString(), 'value' => $item->value])->values());
            $videos = Video::where('user_id', $user->id);

            return ['period_days' => $days, 'totals' => ['profile_views' => $user->profile?->views_count ?? 0, 'video_views' => (clone $videos)->sum('views_count'), 'likes' => (clone $videos)->sum('likes_count'), 'comments' => (clone $videos)->sum('comments_count'), 'shares' => (clone $videos)->sum('shares_count'), 'followers' => $user->followers()->count(), 'opportunity_applications' => OpportunityApplication::whereHas('opportunity', fn ($q) => $q->where('posted_by_id', $user->id))->count()], 'daily' => $daily];
        });

        return response()->json(['data' => $data]);
    }

    public function admin(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        $days = $this->days($request);
        $data = Cache::remember("analytics:platform:{$days}", 300, function () use ($days) {
            $from = today()->subDays($days - 1);

            return ['period_days' => $days, 'totals' => DailyMetric::where('dimension_type', 'platform')->whereDate('metric_date', '>=', $from)->selectRaw('metric, SUM(value) as total')->groupBy('metric')->pluck('total', 'metric'), 'daily' => DailyMetric::where('dimension_type', 'platform')->whereDate('metric_date', '>=', $from)->orderBy('metric_date')->get()->groupBy('metric')->map(fn ($items) => $items->map(fn ($item) => ['date' => $item->metric_date->toDateString(), 'value' => $item->value])->values())];
        });

        return response()->json(['data' => $data]);
    }

    private function days(Request $request): int
    {
        $days = $request->integer('days', 30);
        abort_unless(in_array($days,[7, 30, 90],true),422,'Days must be 7, 30, or 90.');

        return $days;
    }
}
