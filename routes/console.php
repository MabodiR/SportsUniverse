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
