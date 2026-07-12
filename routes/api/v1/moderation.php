<?php

use App\Http\Controllers\Api\V1\Moderation\AdminModerationController;
use App\Http\Controllers\Api\V1\Moderation\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('reports', [ReportController::class, 'store'])->middleware('throttle:10,1');
    Route::prefix('admin')->group(function () {
        Route::get('dashboard', [AdminModerationController::class, 'dashboard']);
        Route::get('moderation', [AdminModerationController::class, 'queue']);
        Route::get('moderation/actions', [AdminModerationController::class, 'actions']);
        Route::patch('moderation/media/{media}', [AdminModerationController::class, 'media']);
        Route::patch('moderation/videos/{video}', [AdminModerationController::class, 'video']);
        Route::patch('moderation/reports/{report}', [AdminModerationController::class, 'report']);
        Route::patch('users/{user}/verification', [AdminModerationController::class, 'verify']);
    });
});
