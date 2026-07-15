<?php

use App\Http\Controllers\Api\V1\Opportunities\OpportunityApplicationController;
use App\Http\Controllers\Api\V1\Opportunities\OpportunityController;
use App\Http\Controllers\Api\V1\Opportunities\SavedOpportunityController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('opportunities', [OpportunityController::class, 'index']);
    Route::post('opportunities', [OpportunityController::class, 'store']);
    Route::get('opportunities/mine', [OpportunityController::class, 'mine']);
    Route::get('applications/mine', [OpportunityApplicationController::class, 'mine']);
    Route::get('opportunities/{opportunity}', [OpportunityController::class, 'show']);
    Route::patch('opportunities/{opportunity}', [OpportunityController::class, 'update']);
    Route::delete('opportunities/{opportunity}', [OpportunityController::class, 'destroy']);
    Route::post('opportunities/{opportunity}/cancel', [OpportunityController::class, 'cancel']);
    Route::post('opportunities/{opportunity}/apply', [OpportunityApplicationController::class, 'store']);
    Route::get('opportunities/{opportunity}/applications', [OpportunityApplicationController::class, 'applicants']);
    Route::patch('applications/{application}', [OpportunityApplicationController::class, 'review']);
    Route::post('applications/{application}/withdraw', [OpportunityApplicationController::class, 'withdraw']);
    Route::post('opportunities/{opportunity}/save', [SavedOpportunityController::class, 'store']);
    Route::delete('opportunities/{opportunity}/save', [SavedOpportunityController::class, 'destroy']);
});
