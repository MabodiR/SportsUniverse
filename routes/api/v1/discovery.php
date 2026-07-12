<?php

use App\Http\Controllers\Api\V1\Discovery\ProfileSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('search/profiles', ProfileSearchController::class)->middleware('throttle:60,1');
