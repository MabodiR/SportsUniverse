<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['nullable', 'in:web,expo'],
            'endpoint' => ['nullable', 'string', 'max:2000', 'required_without:token'],
            'token' => ['nullable', 'string', 'max:500', 'required_without:endpoint'],
            'keys.p256dh' => ['nullable', 'string', 'required_if:provider,web'],
            'keys.auth' => ['nullable', 'string', 'required_if:provider,web'],
            'content_encoding' => ['nullable', 'string', 'max:30'],
            'platform' => ['nullable', 'in:ios,android,web'],
            'device_name' => ['nullable', 'string', 'max:160'],
        ]);
        $provider = $data['provider'] ?? (isset($data['token']) ? 'expo' : 'web');
        $endpoint = $data['token'] ?? $data['endpoint'];
        DB::table('push_subscriptions')->updateOrInsert(['endpoint' => $endpoint], [
            'user_id' => $request->user()->id, 'provider' => $provider, 'platform' => $data['platform'] ?? null,
            'device_name' => $data['device_name'] ?? null, 'public_key' => data_get($data, 'keys.p256dh'),
            'auth_token' => data_get($data, 'keys.auth'), 'content_encoding' => $data['content_encoding'] ?? ($provider === 'web' ? 'aes128gcm' : 'expo'),
            'last_seen_at' => now(), 'updated_at' => now(), 'created_at' => now(),
        ]);

        return response()->json(['message' => 'Push notifications enabled.'], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['nullable', 'string', 'required_without:token'], 'token' => ['nullable', 'string', 'required_without:endpoint']]);
        DB::table('push_subscriptions')->where('user_id', $request->user()->id)->where('endpoint', $data['token'] ?? $data['endpoint'])->delete();

        return response()->json([], 204);
    }
}
