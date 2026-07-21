<?php

use App\Http\Controllers\Api\V1\Advertising\AdCampaignController;
use App\Http\Controllers\Api\V1\Advertising\PayFastController;
use App\Http\Controllers\Api\V1\Advertising\CampaignDeliveryController;
use Illuminate\Support\Facades\Route;

Route::post('payments/payfast/notify', [PayFastController::class, 'notify'])->middleware('throttle:120,1')->name('payfast.notify');
Route::get('campaign-settings', [AdCampaignController::class, 'settings']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('campaign-deliveries/{delivery}/impression', [CampaignDeliveryController::class, 'impression'])->middleware('throttle:120,1');
    Route::post('campaign-deliveries/{delivery}/click', [CampaignDeliveryController::class, 'click'])->middleware('throttle:120,1');
    Route::post('campaign-deliveries/{delivery}/conversion', [CampaignDeliveryController::class, 'conversion'])->middleware('throttle:120,1');
    Route::get('campaigns', [AdCampaignController::class, 'index']);
    Route::post('campaigns', [AdCampaignController::class, 'store']);
    Route::patch('campaigns/{campaign}', [AdCampaignController::class, 'update']);
    Route::post('campaigns/{campaign}/cancel', [AdCampaignController::class, 'cancel']);
    Route::post('campaigns/{campaign}/payfast/checkout', [PayFastController::class, 'checkout']);
    Route::post('campaigns/{campaign}/payfast/sandbox-confirm', [PayFastController::class, 'confirmSandbox']);
    Route::post('campaigns/{campaign}/events', [AdCampaignController::class, 'event'])->middleware('throttle:120,1');
    Route::patch('admin/campaigns/{campaign}/review', [AdCampaignController::class, 'review']);
});
