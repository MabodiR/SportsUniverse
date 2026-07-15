<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Onboarding\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
    Route::post('social/exchange', [AuthController::class, 'socialExchange'])->middleware('throttle:10,1');
});
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::put('auth/password', [AuthController::class, 'updatePassword'])->middleware('throttle:6,1');
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::prefix('onboarding')->group(function () {
        Route::put('role', [OnboardingController::class, 'role']);
        Route::put('athlete-details', [OnboardingController::class, 'athleteDetails']);
        Route::put('fan-interests', [OnboardingController::class, 'fanInterests']);
        Route::put('location', [OnboardingController::class, 'location']);
        Route::get('completeness', [OnboardingController::class, 'completeness']);
        Route::post('complete', [OnboardingController::class, 'complete']);
    });
});
