<?php

use App\Http\Controllers\Api\V1\Analytics\AnalyticsController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use App\Http\Controllers\Api\V1\Admin\SystemSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('dashboard', DashboardController::class);
    Route::post('profiles/{slug}/views', [AnalyticsController::class, 'recordProfileView'])->middleware('throttle:60,1');
    Route::get('analytics/me', [AnalyticsController::class, 'creator']);
    Route::get('admin/analytics', [AnalyticsController::class, 'admin']);
    Route::get('admin/system-settings', [SystemSettingsController::class, 'index']);
    Route::patch('admin/system-settings/{section}', [SystemSettingsController::class, 'update']);
});
