<?php

use App\Http\Controllers\Api\V1\Feed\EngagementController;
use App\Http\Controllers\Api\V1\Feed\FeedController;
use App\Http\Controllers\Api\V1\Feed\VideoController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('feed/for-you', [FeedController::class, 'forYou']);
    Route::get('feed/following', [FeedController::class, 'following']);
    Route::get('feed/saved', [VideoController::class, 'saved']);
    Route::post('videos', [VideoController::class, 'store']);
    Route::patch('videos/{video}', [VideoController::class, 'update']);
    Route::get('videos/mine', [VideoController::class, 'mine']);
    Route::get('videos/mine/reposts', [VideoController::class, 'reposts']);
    Route::get('videos/mine/favourites', [VideoController::class, 'favourites']);
    Route::get('videos/mine/liked', [VideoController::class, 'liked']);
    Route::get('videos/{video}', [VideoController::class, 'show']);
    Route::delete('videos/{video}', [VideoController::class, 'destroy']);
    Route::get('videos/{video}/comments', [VideoController::class, 'comments']);
    Route::post('videos/{video}/comments', [EngagementController::class, 'comment']);
    Route::post('videos/{video}/like', [EngagementController::class, 'like']);
    Route::post('videos/{video}/save', [EngagementController::class, 'save']);
    Route::post('videos/{video}/share', [EngagementController::class, 'share']);
    Route::post('videos/{video}/views', [EngagementController::class, 'view']);
    Route::post('profiles/{user}/follow', [EngagementController::class, 'follow']);
    Route::delete('profiles/{user}/follow', [EngagementController::class, 'unfollow']);
});
