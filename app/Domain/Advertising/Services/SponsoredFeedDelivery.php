<?php

namespace App\Domain\Advertising\Services;

use App\Contracts\Feed\SponsoredPostProvider;
use App\Domain\Advertising\Models\AdCampaign;
use App\Domain\Advertising\Models\BoostSetting;
use App\Domain\Feed\Models\FeedPreference;
use App\Domain\Sports\Models\Sport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SponsoredFeedDelivery implements SponsoredPostProvider
{
    public function insert(Collection $posts, Request $request): Collection
    {
        $settings = BoostSetting::current();
        if (! $settings->enabled || $posts->count() < 3) return $posts;

        $viewer = $request->user();
        if (! $viewer) return $posts;
        $viewer?->loadMissing('profile', 'athleteProfile.sport', 'fanProfile');
        $sessionHash = hash('sha256', (string) (($request->hasSession() ? $request->session()->getId() : null) ?: $request->ip()));
        $slots = max(1, intdiv($posts->count(), max(1, $settings->organic_posts_between)));
        $campaigns = $this->eligibleCampaigns($viewer, $sessionHash, $settings)->take($slots);
        if ($campaigns->isEmpty()) return $posts;

        $result = $posts->values();
        foreach ($campaigns->values() as $slot => $campaign) {
            $delivery = $campaign->deliveries()->create([
                'public_id' => (string) Str::ulid(), 'user_id' => $viewer?->id,
                'session_hash' => $sessionHash, 'served_on' => today(), 'served_at' => now(),
                'placement' => 'for_you_feed',
            ]);
            $video = $campaign->video;
            $video->setAttribute('sponsored', [
                'campaign_id' => $campaign->public_id,
                'delivery_id' => $delivery->public_id,
                'label' => 'Sponsored',
                'goal' => $campaign->goal,
                'cta' => $this->cta($campaign->goal),
                'destination_url' => $this->destination($campaign),
            ]);

            // A promoted post must not appear twice on the same page.
            $result = $result->reject(fn ($post) => $post->id === $video->id)->values();
            $position = min($result->count(), (($slot + 1) * $settings->organic_posts_between));
            $result->splice($position, 0, [$video]);
        }

        return $result->values();
    }

    private function eligibleCampaigns(?User $viewer, string $sessionHash, BoostSetting $settings): Collection
    {
        $query = AdCampaign::query()
            ->where('campaign_type', 'post_promotion')->where('status', 'active')
            ->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today())
            ->whereColumn('spent_cents', '<', 'total_budget_cents')
            ->whereHas('payments', fn ($payment) => $payment->where('status', 'paid'))
            ->whereHas('video', fn ($video) => $video->where('status', 'published')->where('visibility', 'public')
                ->where(fn ($post) => $post
                    ->whereHas('media', fn ($media) => $media->where('processing_status', 'ready')->where('moderation_status', 'approved'))
                    ->orWhereHas('images', fn ($media) => $media->where('processing_status', 'ready')->where('moderation_status', 'approved'))))
            ->with('video.user.profile', 'video.user.athleteProfile.sport', 'video.media', 'video.images', 'video.sport')
            ->withSum(['deliveries as spent_today_cents' => fn ($delivery) => $delivery->whereDate('served_on', today())], 'charge_cents')
            ->withCount(['deliveries as viewer_frequency_today' => fn ($delivery) => $delivery->whereDate('served_on', today())
                ->where(fn ($deliveries) => $viewer
                    ? $deliveries->where('user_id', $viewer->id)
                    : $deliveries->where('session_hash', $sessionHash))])
            ->orderBy('impressions_count')->orderBy('id')->limit(100);

        if ($viewer) $query->where('user_id', '!=', $viewer->id);
        $campaigns = $query->get()->filter(fn ($campaign) => (int) ($campaign->spent_today_cents ?? 0) < $this->pacedBudget($campaign->daily_budget_cents));
        if ($campaigns->isEmpty()) return collect();

        $blocked = $viewer ? DB::table('user_blocks')->where(fn ($blocks) => $blocks
            ->where('blocker_id', $viewer->id)->orWhere('blocked_id', $viewer->id))
            ->get()->map(fn ($block) => $block->blocker_id == $viewer->id ? $block->blocked_id : $block->blocker_id)->flip() : collect();
        $preferences = $viewer ? FeedPreference::query()->where('user_id', $viewer->id)->get() : collect();
        $sportNames = Sport::query()->whereIn('id', $campaigns->pluck('audience')->pluck('sport_id')->filter())->pluck('name', 'id');

        return $campaigns->filter(function (AdCampaign $campaign) use ($viewer, $sessionHash, $settings, $blocked, $preferences, $sportNames) {
            if ($blocked->has($campaign->user_id)) return false;
            if ($preferences->contains(fn ($preference) => $preference->video_id === $campaign->video_id
                || ($preference->scope === 'creator' && $preference->creator_id === $campaign->user_id)
                || ($preference->scope === 'sport' && $preference->sport_id === $campaign->video?->sport_id))) return false;

            if ((int) $campaign->viewer_frequency_today >= $settings->frequency_cap_per_day) return false;

            return $this->matchesAudience($campaign->audience ?? [], $viewer, $sportNames);
        });
    }

    private function matchesAudience(array $audience, ?User $viewer, Collection $sportNames): bool
    {
        $sportId = (int) ($audience['sport_id'] ?? 0);
        $gender = $audience['gender'] ?? 'all';
        $province = trim((string) ($audience['province'] ?? ''));
        $minAge = (int) ($audience['min_age'] ?? 0);
        $maxAge = (int) ($audience['max_age'] ?? 0);

        $isBroad = ! $sportId && in_array($gender, ['', 'all'], true) && $province === '' && ! $minAge && ! $maxAge;
        if (! $viewer) return $isBroad;

        if ($sportId) {
            $athleteMatch = (int) $viewer->athleteProfile?->sport_id === $sportId;
            $targetName = mb_strtolower((string) $sportNames->get($sportId));
            $fanMatch = collect($viewer->fanProfile?->interested_sports ?? [])->contains(fn ($name) => mb_strtolower((string) $name) === $targetName);
            if (! $athleteMatch && ! $fanMatch) return false;
        }
        if (! in_array($gender, ['', 'all'], true) && mb_strtolower((string) $viewer->profile?->gender) !== mb_strtolower($gender)) return false;
        if ($province !== '' && mb_strtolower((string) $viewer->profile?->province) !== mb_strtolower($province)) return false;
        if ($minAge || $maxAge) {
            $age = $viewer->profile?->date_of_birth?->age;
            if ($age === null || ($minAge && $age < $minAge) || ($maxAge && $age > $maxAge)) return false;
        }
        return true;
    }

    private function cta(string $goal): string
    {
        return match ($goal) {
            'followers' => 'View profile', 'website' => 'Learn more',
            'applications' => 'Apply now', default => 'Watch now',
        };
    }

    private function destination(AdCampaign $campaign): string
    {
        if ($campaign->destination_url) return $campaign->destination_url;
        return $campaign->video?->user?->profile?->slug
            ? url('/@'.$campaign->video->user->profile->slug)
            : url('/feed#'.$campaign->video?->public_id);
    }

    private function pacedBudget(int $dailyBudget): int
    {
        $dayProgress = (now()->secondsSinceMidnight() / 86400) + .05;
        return min($dailyBudget, max(1, (int) ceil($dailyBudget * $dayProgress)));
    }
}
