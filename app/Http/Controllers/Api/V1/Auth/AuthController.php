<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Profiles\Actions\EnsureProfileSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, EnsureProfileSlug $slugs): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create($request->safe()->except(['password_confirmation', 'device_name']));
            $user->profile()->create(['completeness' => 20]);

            return $user;
        });
        $slugs->execute($user->load('profile'));

        return response()->json(['message' => 'Account created. Continue onboarding.', 'token' => $user->createToken($request->string('device_name')->value() ?: 'api')->plainTextToken, 'data' => new UserResource($user->load('roles', 'profile'))], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $field = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user = User::where($field, $request->login)->first();
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['login' => ['The supplied credentials are invalid.']]);
        }
        abort_if($user->status !== 'active', 403, 'This account is not active.');

        return response()->json(['token' => $user->createToken($request->string('device_name')->value() ?: 'api')->plainTextToken, 'data' => new UserResource($user->load('roles', 'profile'))]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'If an account exists for that email address, a password reset link has been sent.']);
    }

    public function socialExchange(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:64'], 'device_name' => ['nullable', 'string', 'max:120']]);
        $handoff = Cache::pull('mobile-social:'.$data['code']);
        abort_unless(is_array($handoff) && isset($handoff['user_id']), 422, 'This social sign-in link is invalid or has expired.');
        $user = User::findOrFail($handoff['user_id']);
        abort_if($user->status !== 'active', 403, 'This account is not active.');
        $device = $data['device_name'] ?? $handoff['device_name'] ?? 'mobile';

        return response()->json(['token' => $user->createToken($device)->plainTextToken, 'data' => new UserResource($user->load('roles', 'profile'))]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles', 'profile'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);
        $user = $request->user();
        $user->update(['password' => Hash::make($data['password'])]);
        if ($currentTokenId = $user->currentAccessToken()?->id) {
            $user->tokens()->whereKeyNot($currentTokenId)->delete();
        }

        return response()->json(['message' => 'Password updated. Other signed-in devices have been logged out.']);
    }
}
