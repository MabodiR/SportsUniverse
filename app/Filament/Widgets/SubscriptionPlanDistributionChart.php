<?php

namespace App\Filament\Widgets;

use App\Domain\Subscriptions\Models\SubscriptionPlan;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class SubscriptionPlanDistributionChart extends ChartWidget
{
    protected ?string $heading = 'Members by plan';
    protected ?string $description = 'Current active membership distribution';
    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        return Cache::remember('admin.dashboard.plan-distribution', now()->addMinutes(5), function () {
        $plans = SubscriptionPlan::query()->where('is_active', true)->withCount(['subscriptions as active_count' => fn ($query) => $query->current()])->orderBy('sort_order')->get();
        return ['datasets' => [[
            'label' => 'Members', 'data' => $plans->pluck('active_count')->all(),
            'backgroundColor' => $plans->map(fn ($plan) => match ($plan->slug) {'pro' => '#476FEA', 'elite' => '#86C0ED', default => '#1B212D'})->all(),
            'borderWidth' => 0,
        ]], 'labels' => $plans->pluck('name')->all()];
        });
    }

    protected function getType(): string { return 'doughnut'; }
}
