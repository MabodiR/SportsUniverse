<?php

use App\Http\Controllers\Api\V1\Club\ClubWorkspaceController;
use App\Http\Controllers\Api\V1\Club\PublicClubController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('clubs', [PublicClubController::class, 'index']);
    Route::get('clubs/{slug}', [PublicClubController::class, 'show']);
});

Route::middleware('auth:sanctum')->prefix('club-tools')->group(function () {
    Route::get('/', [ClubWorkspaceController::class, 'overview']);
    Route::patch('/', [ClubWorkspaceController::class, 'update']);
    Route::post('/shortlists', [ClubWorkspaceController::class, 'createShortlist']);
    Route::post('/shortlists/{id}/athletes', [ClubWorkspaceController::class, 'addAthlete']);
    Route::get('/athletes/{athlete}/notes', [ClubWorkspaceController::class, 'notes']);
    Route::post('/notes', [ClubWorkspaceController::class, 'note']);
    Route::post('/compare', [ClubWorkspaceController::class, 'compare']);
    Route::post('/invitations', [ClubWorkspaceController::class, 'invite']);
    Route::post('/staff', [ClubWorkspaceController::class, 'staff']);
    Route::patch('/staff/{staff}', [ClubWorkspaceController::class, 'updateStaff']);
    Route::delete('/staff/{staff}', [ClubWorkspaceController::class, 'removeStaff']);
    Route::get('/pipeline', [ClubWorkspaceController::class, 'pipeline']);
    Route::patch('/pipeline/{application}', [ClubWorkspaceController::class, 'move']);
});
