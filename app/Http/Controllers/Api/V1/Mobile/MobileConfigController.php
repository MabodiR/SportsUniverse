<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MobileConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            'android' => [
                'minimum_version' => config('services.mobile_app.android_minimum_version', '1.0.0'),
                'latest_version' => config('services.mobile_app.android_latest_version', '1.0.0'),
                'download_url' => config('services.mobile_app.android_url') ?: config('services.mobile_app.direct_url'),
            ],
            'ios' => [
                'minimum_version' => config('services.mobile_app.ios_minimum_version', '1.0.0'),
                'latest_version' => config('services.mobile_app.ios_latest_version', '1.0.0'),
                'download_url' => config('services.mobile_app.ios_url'),
            ],
            'release_notes' => config('services.mobile_app.release_notes'),
        ]]);
    }
}
