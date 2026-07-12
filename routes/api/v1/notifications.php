<?php

use App\Http\Controllers\Api\V1\Notifications\NotificationController;
use App\Http\Controllers\Api\V1\Notifications\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read']);
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);
    Route::get('notification-preferences', [NotificationController::class, 'preferences']);
    Route::patch('notification-preferences', [NotificationController::class, 'updatePreferences']);
    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy']);
});
