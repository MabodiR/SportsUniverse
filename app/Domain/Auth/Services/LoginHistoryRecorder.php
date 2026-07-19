<?php

namespace App\Domain\Auth\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoginHistoryRecorder
{
    public function record(User $user, Request $request, string $method = 'password', ?int $tokenId = null): void
    {
        $device = $this->device((string) $request->userAgent());
        $countryCode = $this->header($request, ['CF-IPCountry', 'CloudFront-Viewer-Country', 'X-Country-Code']);
        DB::table('login_histories')->insert([
            'user_id' => $user->id,
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'token_id' => $tokenId,
            'method' => $method,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'browser' => $device['browser'],
            'platform' => $device['platform'],
            'device_type' => $device['type'],
            'country_code' => $countryCode && strlen($countryCode) === 2 ? strtoupper($countryCode) : null,
            'country' => $this->header($request, ['CF-IPCountry-Name', 'X-Country-Name']),
            'region' => $this->header($request, ['CF-Region', 'X-Region', 'X-AppEngine-Region']),
            'city' => $this->header($request, ['CF-IPCity', 'X-City', 'X-AppEngine-City']),
            'logged_in_at' => now(),
        ]);
    }

    public function device(string $agent): array
    {
        $browser = str_contains($agent, 'Edg/') ? 'Edge' : (str_contains($agent, 'Chrome/') ? 'Chrome' : (str_contains($agent, 'Firefox/') ? 'Firefox' : (str_contains($agent, 'Safari/') ? 'Safari' : (str_contains($agent, 'SportsUniverse') ? 'SportsUniverse' : 'Browser'))));
        $platform = preg_match('/iPhone|iPad/', $agent) ? 'iOS' : (str_contains($agent, 'Android') ? 'Android' : (str_contains($agent, 'Windows') ? 'Windows' : (str_contains($agent, 'Macintosh') ? 'macOS' : (str_contains($agent, 'Linux') ? 'Linux' : 'Unknown'))));
        $type = preg_match('/Mobile|Android|iPhone/', $agent) ? 'mobile' : (str_contains($agent, 'iPad') ? 'tablet' : 'desktop');
        return compact('browser', 'platform', 'type');
    }

    private function header(Request $request, array $names): ?string
    {
        foreach ($names as $name) if ($value = $request->header($name)) return mb_substr(trim($value), 0, 120);
        return null;
    }
}
