<?php

use App\Http\Controllers\Api\V1\Profiles\ProfileController;
use App\Http\Controllers\Api\V1\Sports\SportController;
use Illuminate\Support\Facades\Route;

Route::get('sports', [SportController::class, 'index']);
Route::get('profiles/{slug}', [ProfileController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile', [ProfileController::class, 'mine']);
    Route::patch('profile', [ProfileController::class, 'update']);
    Route::post('profile/photo', [ProfileController::class, 'photo']);
    Route::patch('profile/role', [ProfileController::class, 'role']);
    Route::patch('profile/athlete', [ProfileController::class, 'updateAthlete']);
});
