<?php

namespace App\Filament\Widgets;

use App\Domain\Subscriptions\Models\Subscription;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class SubscriptionMovementChart extends ChartWidget
{
    protected ?string $heading = 'Subscriptions and churn';
    protected ?string $description = 'Starts, cancellations, and expiries over the last 12 months';
    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $first = CarbonImmutable::now()->startOfMonth()->subMonths(11);
        $months = collect(range(0, 11))->map(fn ($offset) => $first->addMonths($offset));
        $subscriptions = Subscription::query()->where(fn ($query) => $query->where('starts_at', '>=', $first)->orWhere('cancelled_at', '>=', $first)->orWhere('ends_at', '>=', $first))->get(['starts_at', 'cancelled_at', 'ends_at', 'status']);
        $count = fn (string $field, CarbonImmutable $month, ?string $status = null) => $subscriptions->filter(fn ($subscription) => $subscription->{$field} && $subscription->{$field}->format('Y-m') === $month->format('Y-m') && (! $status || $subscription->status === $status))->count();

        return ['datasets' => [
            ['label' => 'Started', 'data' => $months->map(fn ($month) => $count('starts_at', $month))->all(), 'borderColor' => '#476FEA', 'backgroundColor' => '#476FEA', 'tension' => .35],
            ['label' => 'Cancelled', 'data' => $months->map(fn ($month) => $count('cancelled_at', $month))->all(), 'borderColor' => '#B96B9D', 'backgroundColor' => '#B96B9D', 'tension' => .35],
            ['label' => 'Expired', 'data' => $months->map(fn ($month) => $count('ends_at', $month, 'expired'))->all(), 'borderColor' => '#E2B344', 'backgroundColor' => '#E2B344', 'tension' => .35],
        ], 'labels' => $months->map(fn ($month) => $month->format('M Y'))->all()];
    }

    protected function getType(): string { return 'line'; }
}
