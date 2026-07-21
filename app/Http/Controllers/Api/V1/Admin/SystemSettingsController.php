<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Advertising\Models\BoostSetting;
use App\Domain\Feed\Models\FeedSetting;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SystemSettingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->admin($request);
        $feed = FeedSetting::current();
        $advertising = BoostSetting::current();

        return response()->json(['data' => [
            'feed' => $feed->only(['ranking_mode', 'view_weight', 'like_weight', 'comment_weight', 'share_weight', 'follow_boost', 'page_size', 'recommendation_size', 'use_fan_sports', 'updated_at']),
            'advertising' => $advertising->only(['enabled', 'cpm_cents', 'organic_posts_between', 'frequency_cap_per_day', 'minimum_daily_budget_cents', 'maximum_daily_budget_cents', 'maximum_duration_days', 'require_review', 'updated_at']),
            'environment' => [
                'app_environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'payfast_sandbox' => (bool) config('payfast.sandbox'),
                'queue' => config('queue.default'),
                'cache' => config('cache.default'),
                'mail' => config('mail.default'),
            ],
            'health' => [
                'users' => DB::table('users')->count(),
                'published_videos' => DB::table('videos')->where('status', 'published')->count(),
                'pending_reports' => DB::table('reports')->where('status', 'open')->count(),
                'pending_campaigns' => DB::table('ad_campaigns')->whereIn('status', ['awaiting_payment', 'pending_review'])->count(),
                'failed_jobs' => DB::getSchemaBuilder()->hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
            ],
        ]]);
    }

    public function update(Request $request, string $section): JsonResponse
    {
        $this->admin($request);

        if ($section === 'feed') {
            $data = $request->validate([
                'ranking_mode' => ['required', Rule::in(['personalized', 'engagement', 'recent'])],
                'use_fan_sports' => ['required', 'boolean'],
                'view_weight' => ['required', 'numeric', 'between:0,1000'], 'like_weight' => ['required', 'numeric', 'between:0,1000'],
                'comment_weight' => ['required', 'numeric', 'between:0,1000'], 'share_weight' => ['required', 'numeric', 'between:0,1000'],
                'follow_boost' => ['required', 'numeric', 'between:0,100000'], 'page_size' => ['required', 'integer', 'between:5,50'],
                'recommendation_size' => ['required', 'integer', 'between:50,2000'],
            ]);
            FeedSetting::current()->update([...$data, 'updated_by' => $request->user()->id]);
            return response()->json(['message' => 'Feed settings saved. Recommendation caches will rebuild automatically.']);
        }

        if ($section === 'advertising') {
            $data = $request->validate([
                'enabled' => ['required', 'boolean'], 'require_review' => ['required', 'boolean'],
                'cpm_cents' => ['required', 'integer', 'between:100,1000000'],
                'minimum_daily_budget_cents' => ['required', 'integer', 'min:100'],
                'maximum_daily_budget_cents' => ['required', 'integer', 'gt:minimum_daily_budget_cents'],
                'maximum_duration_days' => ['required', 'integer', 'between:1,180'],
                'organic_posts_between' => ['required', 'integer', 'between:3,50'],
                'frequency_cap_per_day' => ['required', 'integer', 'between:1,20'],
            ]);
            BoostSetting::current()->update([...$data, 'updated_by' => $request->user()->id]);
            return response()->json(['message' => 'Advertising settings saved.']);
        }

        abort(404, 'Unknown settings section.');
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'system_admin', 'super_admin']), 403);
    }
}
