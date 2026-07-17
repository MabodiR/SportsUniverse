<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($currentToken = $request->user()->currentAccessToken()) {
            return response()->json(['data' => $request->user()->tokens()->latest()->get()->map(fn ($token) => [
                ...$this->tokenDevice((string) $token->name),
                'id' => (string) $token->id,
                'ip_address' => null,
                'current' => (int) $currentToken->id === (int) $token->id,
                'last_active_at' => ($token->last_used_at ?? $token->created_at)->toISOString(),
                'active_now' => $token->last_used_at?->greaterThanOrEqualTo(now()->subMinutes(5)) ?? false,
            ])->values()]);
        }

        $current = $request->session()->getId();
        $sessions = collect();
        if (config('session.driver') === 'database') {
            $sessions = DB::table(config('session.table', 'sessions'))->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get();
        }
        if (! $sessions->contains('id', $current)) {
            $sessions->prepend((object) ['id' => $current, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'last_activity' => now()->timestamp]);
        }

        return response()->json(['data' => $sessions->map(fn ($session) => [...$this->device((string) $session->user_agent), 'id' => $session->id, 'ip_address' => $session->ip_address, 'current' => hash_equals($current, $session->id), 'last_active_at' => now()->setTimestamp($session->last_activity)->toISOString(), 'active_now' => $session->last_activity >= now()->subMinutes(5)->timestamp])->values()]);
    }

    public function destroy(Request $request, string $session): JsonResponse
    {
        if ($currentToken = $request->user()->currentAccessToken()) {
            abort_if((int) $currentToken->id === (int) $session, 422, 'Use Sign out to end your current session.');
            $request->user()->tokens()->whereKey($session)->delete();

            return response()->json(['message' => 'Device signed out.']);
        }

        abort_if(hash_equals($request->session()->getId(), $session), 422, 'Use Sign out to end your current session.');
        abort_unless(config('session.driver') === 'database', 409, 'Session revocation requires database sessions.');
        DB::table(config('session.table', 'sessions'))->where('id', $session)->where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Device signed out.']);
    }

    public function destroyOthers(Request $request): JsonResponse
    {
        if ($currentToken = $request->user()->currentAccessToken()) {
            $count = $request->user()->tokens()->whereKeyNot($currentToken->id)->delete();

            return response()->json(['message' => "$count other session(s) signed out."]);
        }

        abort_unless(config('session.driver') === 'database', 409, 'Session revocation requires database sessions.');
        $count = DB::table(config('session.table', 'sessions'))->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();

        return response()->json(['message' => "$count other session(s) signed out."]);
    }

    private function device(string $agent): array
    {
        $browser = str_contains($agent, 'Edg/') ? 'Edge' : (str_contains($agent, 'Chrome/') ? 'Chrome' : (str_contains($agent, 'Firefox/') ? 'Firefox' : (str_contains($agent, 'Safari/') ? 'Safari' : 'Browser')));
        $platform = preg_match('/iPhone|iPad/', $agent) ? 'iOS' : (str_contains($agent, 'Android') ? 'Android' : (str_contains($agent, 'Windows') ? 'Windows' : (str_contains($agent, 'Macintosh') ? 'macOS' : (str_contains($agent, 'Linux') ? 'Linux' : 'Unknown'))));
        $type = preg_match('/Mobile|Android|iPhone/', $agent) ? 'mobile' : (str_contains($agent, 'iPad') ? 'tablet' : 'desktop');

        return compact('browser', 'platform', 'type');
    }

    private function tokenDevice(string $name): array
    {
        $platform = str_contains(strtolower($name), 'ios') ? 'iOS' : (str_contains(strtolower($name), 'android') ? 'Android' : 'Mobile app');

        return ['browser' => 'SportUniverse', 'platform' => $platform, 'type' => 'mobile'];
    }
}
