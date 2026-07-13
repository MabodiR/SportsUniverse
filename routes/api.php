<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(base_path('routes/api/v1/auth.php'));
Route::prefix('v1')->group(base_path('routes/api/v1/profiles.php'));
Route::prefix('v1')->group(base_path('routes/api/v1/media.php'));
Route::prefix('v1')->group(base_path('routes/api/v1/feed.php'));
Route::prefix('v1')->group(base_path('routes/api/v1/discovery.php'));
Route::prefix('v1')->group(base_path('routes/api/v1/messaging.php'));
Route::prefix('v1')->group(base_path('routes/api/v1/opportunities.php'));
Route::prefix('v1')->group(base_path('routes/api/v1/notifications.php'));
Route::prefix('v1')->group(base_path('routes/api/v1/moderation.php'));
Route::prefix('v1')->group(base_path('routes/api/v1/analytics.php'));
Route::prefix('v1')->group(base_path('routes/api/v1/club.php'));
Route::prefix('v1')->group(base_path('routes/api/v1/live.php'));
