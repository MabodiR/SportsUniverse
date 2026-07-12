<?php

use App\Http\Controllers\Api\V1\Discovery\ProfileSearchController;
use App\Http\Controllers\Api\V1\Discovery\UnifiedDiscoveryController;
use App\Http\Controllers\Api\V1\Discovery\SearchLibraryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('search/profiles', ProfileSearchController::class)->middleware('throttle:60,1');
Route::middleware('auth:sanctum')->group(function(){Route::get('search/all',UnifiedDiscoveryController::class)->middleware('throttle:60,1');Route::get('search/history',[SearchLibraryController::class,'history']);Route::delete('search/history',[SearchLibraryController::class,'clear']);Route::get('saved-searches',[SearchLibraryController::class,'saved']);Route::post('saved-searches',[SearchLibraryController::class,'store']);Route::delete('saved-searches/{id}',[SearchLibraryController::class,'destroy']);});
