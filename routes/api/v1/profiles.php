<?php

use App\Http\Controllers\Api\V1\Profiles\ProfileController;
use App\Http\Controllers\Api\V1\Profiles\AthleteCareerController;
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
    Route::patch('profile/professional', [ProfileController::class, 'updateProfessional']);
    Route::patch('profile/organisation', [ProfileController::class, 'updateOrganisation']);
    Route::get('profile/career', [AthleteCareerController::class, 'index']);
    Route::post('profile/career/history', [AthleteCareerController::class, 'storeHistory']);
    Route::delete('profile/career/history/{entry}', [AthleteCareerController::class, 'destroyHistory']);
    Route::post('profile/career/achievements', [AthleteCareerController::class, 'storeAchievement']);
    Route::delete('profile/career/achievements/{achievement}', [AthleteCareerController::class, 'destroyAchievement']);
    Route::post('profile/career/statistics', [AthleteCareerController::class, 'storeStatistic']);
    Route::delete('profile/career/statistics/{statistic}', [AthleteCareerController::class, 'destroyStatistic']);
});
