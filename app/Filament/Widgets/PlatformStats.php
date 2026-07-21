<?php

namespace App\Filament\Widgets;

use App\Domain\Advertising\Models\AdCampaign;
use App\Domain\Feed\Models\Video;
use App\Domain\Media\Models\Media;
use App\Domain\Moderation\Models\Report;
use App\Domain\Opportunities\Models\Opportunity;
use App\Domain\Profiles\Models\OrganisationProfile;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class PlatformStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $counts = Cache::remember('admin.dashboard.platform-stats', now()->addMinute(), fn () => [
            'members' => User::count(),
            'suspended' => User::where('status', 'suspended')->count(),
            'videos' => Video::where('status', 'published')->count(),
            'moderation' => Media::where('moderation_status', 'pending')->count(),
            'reports' => Report::whereIn('status', ['open', 'reviewing'])->count(),
            'campaigns' => AdCampaign::where('status', 'pending_review')->count(),
            'sponsors' => OrganisationProfile::where('organisation_type', 'sponsor')->count(),
            'opportunities' => Opportunity::where('status', 'published')->count(),
        ]);

        return [
            Stat::make('Members', number_format($counts['members']))->description(number_format($counts['suspended']).' suspended')->url('/admin/users'),
            Stat::make('Published videos', number_format($counts['videos']))->description('Visible in feeds'),
            Stat::make('Pending moderation', number_format($counts['moderation']))->color('warning')->url('/admin/media'),
            Stat::make('Open reports', number_format($counts['reports']))->color('danger')->url('/admin/reports'),
            Stat::make('Campaigns to review', number_format($counts['campaigns']))->color('warning')->url('/admin/campaigns'),
            Stat::make('Sponsor accounts', number_format($counts['sponsors']))->description('Registered sponsors')->url('/admin/sponsors'),
            Stat::make('Open opportunities', number_format($counts['opportunities']))->color('success'),
        ];
    }
}
