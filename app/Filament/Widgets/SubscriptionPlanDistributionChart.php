<?php

namespace App\Filament\Widgets;

use App\Domain\Subscriptions\Models\SubscriptionPlan;
use Filament\Widgets\ChartWidget;

class SubscriptionPlanDistributionChart extends ChartWidget
{
    protected ?string $heading = 'Members by plan';

    protected ?string $description = 'Current active membership distribution';

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $plans = SubscriptionPlan::query()->orderBy('sort_order')->get();
        $plans->each(fn ($plan) => $plan->setAttribute(
            'active_count',
            $plan->subscriptions()->current()->distinct('user_id')->count('user_id')
        ));

        return ['datasets' => [[
            'label' => 'Members', 'data' => $plans->pluck('active_count')->all(),
            'backgroundColor' => $plans->map(fn ($plan) => match ($plan->slug) {
                'pro' => '#476FEA', 'elite' => '#86C0ED', default => '#1B212D'
            })->all(),
            'borderWidth' => 0,
        ]], 'labels' => $plans->pluck('name')->all()];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
