<?php

use App\Http\Controllers\Api\V1\Messaging\BlockController;
use App\Http\Controllers\Api\V1\Messaging\ConversationController;
use App\Http\Controllers\Api\V1\Messaging\MessageController;
use App\Http\Controllers\Api\V1\Messaging\MessageRequestController;
use App\Http\Controllers\Web\MessagingContextController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('blocked-users', [BlockController::class, 'index']);
    Route::get('message-requests', [MessageRequestController::class, 'index']);
    Route::get('profiles/{user}/messaging-context', MessagingContextController::class);
    Route::post('message-requests', [MessageRequestController::class, 'store'])->middleware('throttle:20,1');
    Route::post('message-requests/{messageRequest}/accept', [MessageRequestController::class, 'accept']);
    Route::post('message-requests/{messageRequest}/decline', [MessageRequestController::class, 'decline']);
    Route::get('conversations', [ConversationController::class, 'index']);
    Route::get('conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('conversations/{conversation}/read', [ConversationController::class, 'read']);
    Route::post('conversations/{conversation}/archive', [ConversationController::class, 'archive']);
    Route::delete('conversations/{conversation}/archive', [ConversationController::class, 'unarchive']);
    Route::post('conversations/{conversation}/mute', [ConversationController::class, 'mute']);
    Route::post('conversations/{conversation}/typing', [ConversationController::class, 'typing'])->middleware('throttle:120,1');
    Route::post('conversations/{conversation}/report', [ConversationController::class, 'report']);
    Route::get('conversations/{conversation}/messages', [MessageController::class, 'index']);
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])->middleware('throttle:60,1');
    Route::post('profiles/{user}/block', [BlockController::class, 'store']);
    Route::delete('profiles/{user}/block', [BlockController::class, 'destroy']);
});
