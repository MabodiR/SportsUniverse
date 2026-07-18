<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Profiles\Actions\EnsureProfileSlug;
use App\Domain\Auth\Services\LoginHistoryRecorder;
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
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, EnsureProfileSlug $slugs, LoginHistoryRecorder $logins): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $role = $request->validated('role');
            $user = User::create($request->safe()->except(['password_confirmation', 'device_name', 'role']));
            $user->profile()->create(['completeness' => 20]);
            if ($role) {
                Role::findOrCreate($role, 'web');
                $user->syncRoles([$role]);
                if (in_array($role, ['coach', 'referee', 'linesman', 'scout', 'agent'], true)) {
                    $user->professionalProfile()->create(['professional_type' => $role]);
                }
                if (in_array($role, ['club', 'academy', 'business', 'sponsor'], true)) {
                    $user->organisationProfile()->create(['organisation_name' => $user->name, 'organisation_type' => $role]);
                }
            }

            return $user;
        });
        $slugs->execute($user->load('profile'));
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            report($exception);
        }

        $token = $user->createToken($request->string('device_name')->value() ?: 'api');
        $logins->record($user, $request, 'registration', $token->accessToken->id);
        return response()->json(['message' => 'Account created. Continue onboarding.', 'token' => $token->plainTextToken, 'data' => new UserResource($user->load('roles', 'profile'))], 201);
    }

    public function login(LoginRequest $request, LoginHistoryRecorder $logins): JsonResponse
    {
        $field = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user = User::where($field, $request->login)->first();
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['login' => ['The supplied credentials are invalid.']]);
        }
        abort_if($user->status !== 'active', 403, 'This account is not active.');

        $token = $user->createToken($request->string('device_name')->value() ?: 'api');
        $logins->record($user, $request, 'password', $token->accessToken->id);
        return response()->json(['token' => $token->plainTextToken, 'data' => new UserResource($user->load('roles', 'profile'))]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'If an account exists for that email address, a password reset link has been sent.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
            $user->tokens()->delete();
        });
        if ($status !== Password::PASSWORD_RESET) throw ValidationException::withMessages(['email' => [__($status)]]);

        return response()->json(['message' => 'Your password has been reset. Sign in with your new password.']);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) return response()->json(['message' => 'Your email address is already verified.', 'data' => ['verified' => true]]);
        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'A new verification link has been sent.', 'data' => ['verified' => false]]);
    }

    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);
        abort_unless(hash_equals($hash, sha1($user->getEmailForVerification())), 403, 'This verification link is invalid.');
        if (! $user->hasVerifiedEmail()) $user->markEmailAsVerified();

        return response()->json(['message' => 'Email verified successfully.', 'data' => ['verified' => true]]);
    }

    public function socialExchange(Request $request, LoginHistoryRecorder $logins): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:64'], 'device_name' => ['nullable', 'string', 'max:120']]);
        $handoff = Cache::pull('mobile-social:'.$data['code']);
        abort_unless(is_array($handoff) && isset($handoff['user_id']), 422, 'This social sign-in link is invalid or has expired.');
        $user = User::findOrFail($handoff['user_id']);
        abort_if($user->status !== 'active', 403, 'This account is not active.');
        $device = $data['device_name'] ?? $handoff['device_name'] ?? 'mobile';

        $token = $user->createToken($device);
        $logins->record($user, $request, 'social', $token->accessToken->id);
        return response()->json(['token' => $token->plainTextToken, 'data' => new UserResource($user->load('roles', 'profile'))]);
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
