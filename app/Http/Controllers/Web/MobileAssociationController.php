<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MobileAssociationController extends Controller
{
    public function apple(): JsonResponse
    {
        $teamId = config('services.mobile_app.apple_team_id');
        abort_unless($teamId, 404);

        return response()->json(['applinks' => ['details' => [[
            'appIDs' => [$teamId.'.com.sportuniverse.mobile'],
            'components' => collect(['/feed*', '/@*', '/clubs/*', '/opportunities*', '/live*', '/posts/*', '/password/reset/*', '/api/v1/auth/email/verify/*'])->map(fn ($path) => ['/' => $path])->all(),
        ]]]])->header('Content-Type', 'application/json');
    }

    public function android(): JsonResponse
    {
        $fingerprint = config('services.mobile_app.android_sha256_fingerprint');
        abort_unless($fingerprint, 404);

        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => ['namespace' => 'android_app', 'package_name' => 'com.sportuniverse.mobile', 'sha256_cert_fingerprints' => [$fingerprint]],
        ]])->header('Content-Type', 'application/json');
    }
}
