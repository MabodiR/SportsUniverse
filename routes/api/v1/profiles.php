<?php

use App\Http\Controllers\Api\V1\Profiles\ProfileController;
use App\Http\Controllers\Api\V1\Profiles\ProfileConnectionController;
use App\Http\Controllers\Api\V1\Profiles\AthleteCareerController;
use App\Http\Controllers\Api\V1\Profiles\FanPreferenceController;
use App\Http\Controllers\Api\V1\Sports\SportController;
use Illuminate\Support\Facades\Route;

Route::get('sports', [SportController::class, 'index']);
Route::get('profiles/{slug}', [ProfileController::class, 'show']);
Route::get('profiles/{slug}/videos', [ProfileController::class, 'videos']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('admin/taxonomy', [SportController::class, 'adminIndex']);
    Route::post('admin/taxonomy/sports', [SportController::class, 'store']);
    Route::post('admin/taxonomy/import-catalogue', [SportController::class, 'importCatalogue']);
    Route::patch('admin/taxonomy/sports/{sport}', [SportController::class, 'update']);
    Route::post('admin/taxonomy/sports/{sport}/positions', [SportController::class, 'storePosition']);
    Route::patch('admin/taxonomy/positions/{position}', [SportController::class, 'updatePosition']);
    Route::get('profiles/{user}/followers', [ProfileConnectionController::class, 'followers']);
    Route::get('profiles/{user}/following', [ProfileConnectionController::class, 'following']);
    Route::get('profile', [ProfileController::class, 'mine']);
    Route::patch('profile', [ProfileController::class, 'update']);
    Route::post('profile/photo', [ProfileController::class, 'photo']);
    Route::post('profile/cover', [ProfileController::class, 'cover']);
    Route::patch('profile/role', [ProfileController::class, 'role']);
    Route::patch('profile/athlete', [ProfileController::class, 'updateAthlete']);
    Route::patch('profile/professional', [ProfileController::class, 'updateProfessional']);
    Route::patch('profile/organisation', [ProfileController::class, 'updateOrganisation']);
    Route::get('profile/career', [AthleteCareerController::class, 'index']);
    Route::get('profile/fan-preferences', [FanPreferenceController::class, 'show']);
    Route::put('profile/fan-preferences', [FanPreferenceController::class, 'update']);
    Route::post('profile/career/history', [AthleteCareerController::class, 'storeHistory']);
    Route::delete('profile/career/history/{entry}', [AthleteCareerController::class, 'destroyHistory']);
    Route::post('profile/career/achievements', [AthleteCareerController::class, 'storeAchievement']);
    Route::delete('profile/career/achievements/{achievement}', [AthleteCareerController::class, 'destroyAchievement']);
    Route::post('profile/career/statistics', [AthleteCareerController::class, 'storeStatistic']);
    Route::delete('profile/career/statistics/{statistic}', [AthleteCareerController::class, 'destroyStatistic']);
});
