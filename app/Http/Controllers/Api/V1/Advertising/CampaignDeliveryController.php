<?php

namespace App\Http\Controllers\Api\V1\Advertising;

use App\Domain\Advertising\Models\BoostSetting;
use App\Domain\Advertising\Models\CampaignDelivery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CampaignDeliveryController extends Controller
{
    public function impression(Request $request, CampaignDelivery $delivery): JsonResponse
    {
        $this->ownsPlacement($request, $delivery);
        DB::transaction(function () use ($delivery) {
            $delivery = CampaignDelivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            if ($delivery->impressed_at) return;
            $campaign = $delivery->campaign()->lockForUpdate()->firstOrFail();
            abort_unless($campaign->status === 'active' && $campaign->starts_on->isPast() && $campaign->ends_on->endOfDay()->isFuture(), 409, 'This promotion is no longer active.');
            $cost = BoostSetting::current()->impressionCost();
            $spentToday = (int) $campaign->deliveries()->whereDate('served_on', today())->sum('charge_cents');
            $pacedBudget = min($campaign->daily_budget_cents, max(1, (int) ceil($campaign->daily_budget_cents * ((now()->secondsSinceMidnight() / 86400) + .05))));
            abort_if($campaign->spent_cents + $cost > $campaign->total_budget_cents || $spentToday + $cost > $pacedBudget, 409, 'This promotion has reached its current delivery budget.');
            $delivery->update(['impressed_at' => now(), 'charge_cents' => $cost]);
            $newSpent = $campaign->spent_cents + $cost;
            $campaign->incrementEach(['impressions_count' => 1, 'spent_cents' => $cost]);
            if ($newSpent >= $campaign->total_budget_cents) $campaign->update(['status' => 'completed']);
        });
        return response()->json(['data' => ['recorded' => true]]);
    }

    public function click(Request $request, CampaignDelivery $delivery): JsonResponse
    {
        $this->ownsPlacement($request, $delivery);
        DB::transaction(function () use ($delivery) {
            $delivery = CampaignDelivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            if ($delivery->clicked_at) return;
            $delivery->update(['clicked_at' => now()]);
            $delivery->campaign()->increment('clicks_count');
        });
        return response()->json(['data' => ['recorded' => true, 'destination_url' => $delivery->campaign->destination_url]]);
    }

    public function conversion(Request $request, CampaignDelivery $delivery): JsonResponse
    {
        $this->ownsPlacement($request, $delivery);
        $event = $request->validate(['event' => ['required', Rule::in(['video_view', 'profile_visit', 'follow'])]])['event'];
        $column = ['video_view' => 'video_viewed_at', 'profile_visit' => 'profile_visited_at', 'follow' => 'followed_at'][$event];
        if (! $delivery->{$column}) $delivery->update([$column => now()]);
        return response()->json(['data' => ['recorded' => true]]);
    }

    private function ownsPlacement(Request $request, CampaignDelivery $delivery): void
    {
        if ($delivery->user_id) abort_unless($request->user()?->id === $delivery->user_id, 403);
        else abort_unless(hash_equals((string) $delivery->session_hash, hash('sha256', (string) (($request->hasSession() ? $request->session()->getId() : null) ?: $request->ip()))), 403);
    }
}
