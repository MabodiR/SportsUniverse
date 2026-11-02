<?php

namespace App\Filament\Widgets;

use App\Domain\Promotions\Models\Voucher;
use App\Domain\Promotions\Models\VoucherRedemption;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PromotionStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $voucher = Voucher::query()
            ->where('auto_apply', true)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()
            ->first();
        $redemptions = $voucher?->redemptions()->count() ?? 0;
        $registrations = User::count();
        $remaining = $voucher?->max_redemptions === null
            ? 'Unlimited'
            : max(0, $voucher->max_redemptions - $redemptions);

        return [
            Stat::make('Promotion audience', number_format($registrations))
                ->description('All registered accounts included in conversion reporting')
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('primary'),
            Stat::make('Registered today', User::whereDate('created_at', today())->count())
                ->description('New accounts created since midnight')
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('success'),
            Stat::make('Registered this week', User::where('created_at', '>=', now()->startOfWeek())->count())
                ->description('New accounts since the start of this week')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('info'),
            Stat::make('Registered this month', User::where('created_at', '>=', now()->startOfMonth())->count())
                ->description('New accounts since the start of this month')
                ->icon(Heroicon::OutlinedCalendar)
                ->color('info'),
            Stat::make('Promotion claims', $redemptions)
                ->description($voucher ? $voucher->campaign_name.' voucher redemptions' : 'No active campaign')
                ->icon(Heroicon::OutlinedGift)
                ->color('primary')
                ->url('/admin/voucher-redemptions'),
            Stat::make('Places remaining', $voucher ? $remaining : 'No active campaign')
                ->description($voucher ? 'Claims still available before the campaign limit' : 'Activate a campaign to start accepting claims')
                ->icon(Heroicon::OutlinedTicket)
                ->color($voucher && is_numeric($remaining) && $remaining < 20 ? 'warning' : 'success')
                ->url('/admin/vouchers'),
            Stat::make('Promotion conversion', $registrations ? number_format(($redemptions / $registrations) * 100, 1).'%' : '0.0%')
                ->description('Share of registered accounts that claimed this campaign')
                ->icon(Heroicon::OutlinedChartBar)
                ->color('info'),
            Stat::make('Active promotional members', VoucherRedemption::whereHas('subscription', fn ($q) => $q->current())->distinct('user_id')->count('user_id'))
                ->description('Unique claimants whose promotional plan is still active')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success'),
        ];
    }
}
