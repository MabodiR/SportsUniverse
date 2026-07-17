<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\AccountDeletionController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Onboarding\OnboardingController;
use App\Http\Controllers\Api\V1\Mobile\MobileConfigController;
use Illuminate\Support\Facades\Route;

Route::get('mobile/config', MobileConfigController::class)->middleware('throttle:60,1');
Route::get('auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['signed', 'throttle:6,1'])->name('api.verification.verify');

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');
    Route::post('social/exchange', [AuthController::class, 'socialExchange'])->middleware('throttle:10,1');
});
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('auth/email/verification-notification', [AuthController::class, 'resendVerification'])->middleware('throttle:6,1');
    Route::get('auth/sessions', [SessionController::class, 'index']);
    Route::delete('auth/sessions/others', [SessionController::class, 'destroyOthers']);
    Route::delete('auth/sessions/{session}', [SessionController::class, 'destroy']);
    Route::get('auth/account-deletion', [AccountDeletionController::class, 'show']);
    Route::post('auth/account-deletion', [AccountDeletionController::class, 'store'])->middleware('throttle:3,60');
    Route::delete('auth/account-deletion', [AccountDeletionController::class, 'destroy']);
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
