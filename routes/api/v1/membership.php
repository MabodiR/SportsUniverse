<?php

use App\Http\Controllers\Web\MembershipPaymentController;
use Illuminate\Support\Facades\Route;

Route::post('membership/payfast/notify', [MembershipPaymentController::class, 'notify'])->middleware('throttle:120,1')->name('membership.payfast.notify');
Route::middleware('auth:sanctum')->post('membership/plans/{plan:slug}/checkout', [MembershipPaymentController::class, 'checkout']);
