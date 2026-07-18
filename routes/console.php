<?php

use App\Domain\Analytics\Jobs\AggregateDailyMetrics;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => AggregateDailyMetrics::dispatch(yesterday()->toDateString()))
    ->name('analytics:daily-rollup')
    ->dailyAt('00:15')
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('db:partition-video-views --months=3')->monthlyOn(1, '00:05')->onOneServer();
Schedule::command('feed:precompute')->hourly()->onOneServer()->withoutOverlapping();
Schedule::command('feed:flush-counters --limit=5000')->everyMinute()->onOneServer()->withoutOverlapping();
