<?php

use App\Http\Controllers\Api\V1\Club\ClubWorkspaceController;
use Illuminate\Support\Facades\Route;

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
    Route::get('/pipeline', [ClubWorkspaceController::class, 'pipeline']);
    Route::patch('/pipeline/{application}', [ClubWorkspaceController::class, 'move']);
});
