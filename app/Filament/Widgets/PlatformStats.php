<?php

namespace App\Filament\Widgets;

use App\Domain\Advertising\Models\AdCampaign;
use App\Domain\Feed\Models\Video;
use App\Domain\Media\Models\Media;
use App\Domain\Moderation\Models\Report;
use App\Domain\Opportunities\Models\Opportunity;
use App\Domain\Profiles\Models\OrganisationProfile;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // Keep the operational dashboard live. These queries intentionally avoid a
        // result cache so an admin action is reflected on the next dashboard load.
        $counts = [
            'members' => User::count(),
            'suspended' => User::where('status', 'suspended')->count(),
            'videos' => Video::where('status', 'published')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->count(),
            'moderation' => Media::where('moderation_status', 'pending')->count(),
            'reports' => Report::whereIn('status', ['open', 'reviewing'])->count(),
            'campaigns' => AdCampaign::where('status', 'pending_review')->count(),
            'sponsors' => OrganisationProfile::where('organisation_type', 'sponsor')->count(),
            'opportunities' => Opportunity::where('status', 'published')
                ->where(fn ($query) => $query->whereNull('deadline')->orWhere('deadline', '>', now()))
                ->count(),
        ];

        return [
            Stat::make('Platform members', number_format($counts['members']))
                ->description(number_format($counts['suspended']).' accounts currently suspended')
                ->icon(Heroicon::OutlinedUsers)
                ->color($counts['suspended'] ? 'warning' : 'primary')
                ->url('/admin/users'),
            Stat::make('Published videos', number_format($counts['videos']))
                ->description('Live, non-expired videos visible in member feeds')
                ->icon(Heroicon::OutlinedVideoCamera)
                ->color('info')
                ->url('/admin/videos'),
            Stat::make('Pending moderation', number_format($counts['moderation']))
                ->description('Media items waiting for an administrator decision')
                ->icon(Heroicon::OutlinedShieldExclamation)
                ->color($counts['moderation'] ? 'warning' : 'success')
                ->url('/admin/media'),
            Stat::make('Open reports', number_format($counts['reports']))
                ->description('Member reports that are open or under review')
                ->icon(Heroicon::OutlinedFlag)
                ->color($counts['reports'] ? 'danger' : 'success')
                ->url('/admin/reports'),
            Stat::make('Campaigns to review', number_format($counts['campaigns']))
                ->description('Advertising campaigns awaiting approval')
                ->icon(Heroicon::OutlinedMegaphone)
                ->color($counts['campaigns'] ? 'warning' : 'success')
                ->url('/admin/campaigns'),
            Stat::make('Sponsor profiles', number_format($counts['sponsors']))
                ->description('Registered sponsor organisation profiles')
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->color('primary')
                ->url('/admin/sponsors'),
            Stat::make('Open opportunities', number_format($counts['opportunities']))
                ->description('Published opportunities still accepting applications')
                ->icon(Heroicon::OutlinedBriefcase)
                ->color('success')
                ->url('/admin/opportunities'),
        ];
    }
}
