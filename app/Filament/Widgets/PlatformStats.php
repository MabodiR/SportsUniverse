<?php

namespace App\Filament\Widgets;

use App\Domain\Feed\Models\Video;
use App\Domain\Media\Models\Media;
use App\Domain\Moderation\Models\Report;
use App\Domain\Opportunities\Models\Opportunity;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Members', User::count())->description('Registered accounts'),
            Stat::make('Published videos', Video::where('status', 'published')->count())->description('Visible in feeds'),
            Stat::make('Pending moderation', Media::where('moderation_status', 'pending')->count())->color('warning'),
            Stat::make('Open reports', Report::where('status', 'open')->count())->color('danger'),
            Stat::make('Open opportunities', Opportunity::where('status', 'published')->count())->color('success'),
        ];
    }
}
