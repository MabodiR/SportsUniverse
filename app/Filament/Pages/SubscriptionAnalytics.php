<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SubscriptionMovementChart;
use App\Filament\Widgets\SubscriptionPlanDistributionChart;
use App\Filament\Widgets\SubscriptionRevenueChart;
use App\Filament\Widgets\SubscriptionStats;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SubscriptionAnalytics extends Page
{
    protected static ?string $title = 'Subscription analytics';
    protected static ?string $navigationLabel = 'Subscription analytics';
    protected static ?string $slug = 'subscription-analytics';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;
    protected static string|UnitEnum|null $navigationGroup = 'Membership';
    protected static ?int $navigationSort = 90;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['system_admin', 'super_admin']);
    }

    protected function getHeaderWidgets(): array
    {
        return [SubscriptionStats::class, SubscriptionPlanDistributionChart::class, SubscriptionMovementChart::class, SubscriptionRevenueChart::class];
    }

    public function getHeaderWidgetsColumns(): int|array { return ['md' => 2, 'xl' => 2]; }
}
