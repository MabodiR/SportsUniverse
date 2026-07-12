<?php

use App\Http\Controllers\Api\V1\Analytics\AnalyticsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('profiles/{slug}/views', [AnalyticsController::class, 'recordProfileView'])->middleware('throttle:60,1');
    Route::get('analytics/me', [AnalyticsController::class, 'creator']);
    Route::get('admin/analytics', [AnalyticsController::class, 'admin']);
});
