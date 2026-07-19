<?php

namespace App\Filament\Widgets;

use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Models\SubscriptionPayment;
use App\Domain\Subscriptions\Models\SubscriptionPlan;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubscriptionStats extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $plans = SubscriptionPlan::query()->withCount(['subscriptions as active_count' => fn ($query) => $query->current()])->orderBy('sort_order')->get();
        $active = $plans->sum('active_count');
        $paidMembers = $plans->where('monthly_price_cents', '>', 0)->sum('active_count');
        $paidPayments = SubscriptionPayment::query()->where('status', 'paid');
        $revenueCents = (clone $paidPayments)->sum('amount_cents');
        $monthRevenueCents = (clone $paidPayments)->where('paid_at', '>=', now()->startOfMonth())->sum('amount_cents');
        $attempts = SubscriptionPayment::query()->where('created_at', '>=', now()->subDays(30))->count();
        $completed = SubscriptionPayment::query()->where('created_at', '>=', now()->subDays(30))->where('status', 'paid')->count();
        $cancelled = Subscription::query()->whereNotNull('cancelled_at')->where('cancelled_at', '>=', now()->subDays(30))->count();
        $started = Subscription::query()->where('starts_at', '>=', now()->subDays(30))->count();

        $stats = $plans->map(fn ($plan) => Stat::make($plan->name.' members', number_format($plan->active_count))
            ->description($active ? number_format(($plan->active_count / $active) * 100, 1).'% of active memberships' : 'No active members')
            ->color(match ($plan->slug) {'pro' => 'primary', 'elite' => 'info', default => 'gray'}))->all();

        return [...$stats,
            Stat::make('Paid members', number_format($paidMembers))->description($active ? number_format(($paidMembers / $active) * 100, 1).'% paid-plan adoption' : '0% paid-plan adoption')->color('success'),
            Stat::make('Revenue this month', 'R '.number_format($monthRevenueCents / 100, 2))->description('R '.number_format($revenueCents / 100, 2).' verified lifetime revenue')->color('success')->url('/admin/subscription-payments'),
            Stat::make('30-day payment conversion', $attempts ? number_format(($completed / $attempts) * 100, 1).'%' : '—')->description(number_format($completed).' of '.number_format($attempts).' PayFast attempts completed'),
            Stat::make('30-day churn signal', $started ? number_format(($cancelled / $started) * 100, 1).'%' : '0.0%')->description(number_format($cancelled).' cancellations vs '.number_format($started).' starts')->color($cancelled ? 'warning' : 'success'),
        ];
    }
}
