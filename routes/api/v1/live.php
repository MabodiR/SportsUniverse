<?php

use App\Http\Controllers\Api\V1\LiveStreamController;
use Illuminate\Support\Facades\Route;

Route::get('/live', [LiveStreamController::class, 'index']);
Route::get('/live/{stream}', [LiveStreamController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/live', [LiveStreamController::class, 'store']);
    Route::post('/live/{stream}/join', [LiveStreamController::class, 'join']);
    Route::post('/live/{stream}/messages', [LiveStreamController::class, 'message']);
    Route::post('/live/{stream}/signal', [LiveStreamController::class, 'signal']);
    Route::post('/live/{stream}/heartbeat', [LiveStreamController::class, 'heartbeat']);
    Route::post('/live/{stream}/end', [LiveStreamController::class, 'end']);
});
