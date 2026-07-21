<?php

namespace App\Http\Controllers\Api\V1\Advertising;

use App\Domain\Advertising\Models\AdCampaign;
use App\Domain\Advertising\Models\BoostSetting;
use App\Domain\Advertising\Services\PayFastGateway;
use App\Domain\Feed\Models\Video;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdCampaignController extends Controller
{
    public function settings(): JsonResponse
    {
        $settings = BoostSetting::current();
        return response()->json(['data' => [
            'enabled' => $settings->enabled,
            'cpm_cents' => $settings->cpm_cents,
            'minimum_daily_budget_cents' => $settings->minimum_daily_budget_cents,
            'maximum_daily_budget_cents' => $settings->maximum_daily_budget_cents,
            'maximum_duration_days' => $settings->maximum_duration_days,
            'require_review' => $settings->require_review,
            'sandbox' => (bool) config('payfast.sandbox'),
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        $campaigns = $request->user()->adCampaigns()->with('video.media')->latest()->get()->map(fn ($campaign) => $this->data($campaign));

        return response()->json(['data' => $campaigns]);
    }

    public function store(Request $request, PayFastGateway $payments): JsonResponse
    {
        abort_unless(BoostSetting::current()->enabled, 503, 'Post boosting is temporarily unavailable.');
        $data = $this->validated($request);
        $video = $this->video($request, $data['video_id'] ?? null);
        abort_if($data['campaign_type'] === 'post_promotion' && ! $video, 422, 'Select a published post to promote.');
        abort_if($video?->post_type === 'story' && $video->expires_at?->isPast(), 422, 'This Story has expired. Stories can only be promoted during their 24-hour lifetime.');
        abort_if($video?->post_type === 'story' && now()->parse($data['ends_on'])->endOfDay()->isAfter($video->expires_at), 422, 'A Story promotion cannot run after the Story expires.');
        $days = now()->parse($data['starts_on'])->diffInDays(now()->parse($data['ends_on'])) + 1;
        $campaign = $request->user()->adCampaigns()->create([
            ...collect($data)->except(['video_id', 'submit'])->all(), 'public_id' => (string) Str::ulid(),
            'video_id' => $video?->id, 'daily_budget_cents' => $data['daily_budget_cents'],
            'total_budget_cents' => $data['daily_budget_cents'] * $days,
            'status' => $request->boolean('submit') ? 'awaiting_payment' : 'draft',
            'submitted_at' => null,
        ]);
        $result = $this->data($campaign->load('video.media'));
        if ($request->boolean('submit')) $result['checkout'] = $payments->checkout($campaign, $request->user());
        return response()->json(['message' => $request->boolean('submit') ? 'Campaign created. Complete payment securely with PayFast.' : 'Campaign draft saved.', 'data' => $result], 201);
    }

    public function update(Request $request, AdCampaign $campaign): JsonResponse
    {
        $this->owner($request, $campaign);
        abort_unless(in_array($campaign->status, ['draft', 'rejected'], true), 409, 'Only draft or rejected campaigns can be edited.');
        $data = $this->validated($request);
        $video = $this->video($request, $data['video_id'] ?? null);
        abort_if($data['campaign_type'] === 'post_promotion' && ! $video, 422, 'Select a published post to promote.');
        abort_if($video?->post_type === 'story' && $video->expires_at?->isPast(), 422, 'This Story has expired. Stories can only be promoted during their 24-hour lifetime.');
        abort_if($video?->post_type === 'story' && now()->parse($data['ends_on'])->endOfDay()->isAfter($video->expires_at), 422, 'A Story promotion cannot run after the Story expires.');
        $days = now()->parse($data['starts_on'])->diffInDays(now()->parse($data['ends_on'])) + 1;
        $campaign->update([...collect($data)->except(['video_id', 'submit'])->all(), 'video_id' => $video?->id, 'total_budget_cents' => $data['daily_budget_cents'] * $days, 'status' => $request->boolean('submit') ? 'awaiting_payment' : 'draft', 'submitted_at' => null, 'review_notes' => null]);

        return response()->json(['message' => 'Campaign updated.', 'data' => $this->data($campaign->fresh()->load('video.media'))]);
    }

    public function cancel(Request $request, AdCampaign $campaign): JsonResponse
    {
        $this->owner($request, $campaign);
        abort_if(in_array($campaign->status, ['completed', 'cancelled'], true), 409);
        $campaign->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Campaign cancelled.', 'data' => $this->data($campaign)]);
    }

    public function event(Request $request, AdCampaign $campaign): JsonResponse
    {
        $event = $request->validate(['event' => ['required', Rule::in(['impression', 'click'])]])['event'];
        abort_unless($campaign->status === 'active' && $campaign->starts_on->isPast() && $campaign->ends_on->endOfDay()->isFuture(), 404);
        $campaign->increment($event === 'click' ? 'clicks_count' : 'impressions_count');

        return response()->json(['data' => ['recorded' => true]]);
    }

    public function review(Request $request, AdCampaign $campaign): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'system_admin', 'super_admin']), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'rejected'])], 'review_notes' => ['nullable', 'string', 'max:2000']]);
        abort_if($data['status'] === 'active' && ! $campaign->payments()->where('status', 'paid')->exists(), 409, 'Campaign payment has not been confirmed.');
        $campaign->update([...$data, 'reviewed_at' => now()]);

        return response()->json(['message' => 'Campaign reviewed.', 'data' => $this->data($campaign)]);
    }

    private function validated(Request $request): array
    {
        $settings = BoostSetting::current();
        return $request->validate([
            'campaign_type' => ['required', Rule::in(['post_promotion', 'sponsorship'])], 'video_id' => ['nullable', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:3000'],
            'goal' => ['required', Rule::in(['views', 'followers', 'website', 'applications', 'awareness'])],
            'audience' => ['nullable', 'array'], 'audience.sport_id' => ['nullable', 'integer', 'exists:sports,id'],
            'audience.gender' => ['nullable', Rule::in(['female', 'male', 'all'])], 'audience.province' => ['nullable', 'string', 'max:120'],
            'audience.min_age' => ['nullable', 'integer', 'between:13,100'], 'audience.max_age' => ['nullable', 'integer', 'between:13,100', 'gte:audience.min_age'],
            'destination_url' => ['nullable', 'url:http,https', 'max:255'], 'daily_budget_cents' => ['required', 'integer', 'between:'.$settings->minimum_daily_budget_cents.','.$settings->maximum_daily_budget_cents],
            'starts_on' => ['required', 'date', 'after_or_equal:today'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on', 'before_or_equal:'.today()->addDays($settings->maximum_duration_days - 1)->toDateString()],
            'submit' => ['nullable', 'boolean'],
        ]);
    }

    private function video(Request $request, ?string $id): ?Video
    {
        return $id ? $request->user()->videos()->where('public_id', $id)->where('status', 'published')->first() : null;
    }

    private function owner(Request $request, AdCampaign $campaign): void
    {
        abort_unless($campaign->user_id === $request->user()->id || $request->user()->hasRole('admin'), 403);
    }

    private function data(AdCampaign $campaign): array
    {
        $delivery = $campaign->deliveries();
        return ['id' => $campaign->public_id, 'campaign_type' => $campaign->campaign_type, 'title' => $campaign->title, 'description' => $campaign->description, 'goal' => $campaign->goal, 'audience' => $campaign->audience ?? [], 'destination_url' => $campaign->destination_url, 'daily_budget_cents' => $campaign->daily_budget_cents, 'total_budget_cents' => $campaign->total_budget_cents, 'starts_on' => $campaign->starts_on?->toDateString(), 'ends_on' => $campaign->ends_on?->toDateString(), 'status' => $campaign->status, 'payment_status' => $campaign->payments()->latest()->value('status'), 'review_notes' => $campaign->review_notes, 'metrics' => ['impressions' => $campaign->impressions_count, 'unique_reach' => (clone $delivery)->whereNotNull('impressed_at')->distinct()->count(DB::raw("COALESCE(CAST(user_id AS VARCHAR), session_hash)")), 'clicks' => $campaign->clicks_count, 'click_rate' => $campaign->impressions_count ? round($campaign->clicks_count / $campaign->impressions_count * 100, 2) : 0, 'video_views' => (clone $delivery)->whereNotNull('video_viewed_at')->count(), 'profile_visits' => (clone $delivery)->whereNotNull('profile_visited_at')->count(), 'follows' => (clone $delivery)->whereNotNull('followed_at')->count(), 'spent_cents' => $campaign->spent_cents], 'video' => $campaign->video ? ['id' => $campaign->video->public_id, 'caption' => $campaign->video->caption, 'url' => $campaign->video->media ? route('media.download', $campaign->video->media) : null] : null, 'created_at' => $campaign->created_at];
    }
}
