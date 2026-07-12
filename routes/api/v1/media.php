<?php

use App\Http\Controllers\Api\V1\Media\MediaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('media', [MediaController::class, 'index']);
    Route::post('media', [MediaController::class, 'store'])->middleware('throttle:uploads');
    Route::get('media/{media}', [MediaController::class, 'show']);
    Route::get('media/{media}/download', [MediaController::class, 'download'])->name('media.download');
    Route::delete('media/{media}', [MediaController::class, 'destroy']);
});
