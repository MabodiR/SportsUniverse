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
use Illuminate\Validation\ValidationException;

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

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles', 'profile'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
