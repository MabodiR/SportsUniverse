<?php

use App\Http\Controllers\Api\V1\Advertising\AdCampaignController;
use App\Http\Controllers\Api\V1\Advertising\PayFastController;
use Illuminate\Support\Facades\Route;

Route::post('payments/payfast/notify', [PayFastController::class, 'notify'])->middleware('throttle:120,1')->name('payfast.notify');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('campaigns', [AdCampaignController::class, 'index']);
    Route::post('campaigns', [AdCampaignController::class, 'store']);
    Route::patch('campaigns/{campaign}', [AdCampaignController::class, 'update']);
    Route::post('campaigns/{campaign}/cancel', [AdCampaignController::class, 'cancel']);
    Route::post('campaigns/{campaign}/payfast/checkout', [PayFastController::class, 'checkout']);
    Route::post('campaigns/{campaign}/events', [AdCampaignController::class, 'event'])->middleware('throttle:120,1');
    Route::patch('admin/campaigns/{campaign}/review', [AdCampaignController::class, 'review']);
});
