<?php

namespace App\Filament\Widgets;

use App\Domain\Subscriptions\Models\SubscriptionPayment;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class SubscriptionRevenueChart extends ChartWidget
{
    protected ?string $heading = 'PayFast revenue and conversions';
    protected ?string $description = 'Verified membership revenue with successful payment volume';
    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $first = CarbonImmutable::now()->startOfMonth()->subMonths(11);
        $months = collect(range(0, 11))->map(fn ($offset) => $first->addMonths($offset));
        $payments = SubscriptionPayment::query()->where('created_at', '>=', $first)->get(['amount_cents', 'status', 'paid_at', 'created_at']);

        return ['datasets' => [
            ['type' => 'bar', 'label' => 'Revenue (R)', 'data' => $months->map(fn ($month) => $payments->filter(fn ($payment) => $payment->status === 'paid' && $payment->paid_at?->format('Y-m') === $month->format('Y-m'))->sum('amount_cents') / 100)->all(), 'backgroundColor' => '#77A571', 'borderRadius' => 5, 'yAxisID' => 'y'],
            ['type' => 'line', 'label' => 'Successful payments', 'data' => $months->map(fn ($month) => $payments->filter(fn ($payment) => $payment->status === 'paid' && $payment->paid_at?->format('Y-m') === $month->format('Y-m'))->count())->all(), 'borderColor' => '#476FEA', 'backgroundColor' => '#476FEA', 'tension' => .35, 'yAxisID' => 'y1'],
        ], 'labels' => $months->map(fn ($month) => $month->format('M Y'))->all()];
    }

    protected function getType(): string { return 'bar'; }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['beginAtZero' => true, 'position' => 'left'], 'y1' => ['beginAtZero' => true, 'position' => 'right', 'grid' => ['drawOnChartArea' => false]]]];
    }
}
