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

class PlatformStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Members', User::count())->description(User::where('status', 'suspended')->count().' suspended')->url('/admin/users'),
            Stat::make('Published videos', Video::where('status', 'published')->count())->description('Visible in feeds'),
            Stat::make('Pending moderation', Media::where('moderation_status', 'pending')->count())->color('warning')->url('/admin/media'),
            Stat::make('Open reports', Report::whereIn('status', ['open', 'reviewing'])->count())->color('danger')->url('/admin/reports'),
            Stat::make('Campaigns to review', AdCampaign::where('status', 'pending_review')->count())->color('warning')->url('/admin/campaigns'),
            Stat::make('Sponsor accounts', OrganisationProfile::where('organisation_type', 'sponsor')->count())->description('Registered sponsors')->url('/admin/sponsors'),
            Stat::make('Open opportunities', Opportunity::where('status', 'published')->count())->color('success'),
        ];
    }
}
