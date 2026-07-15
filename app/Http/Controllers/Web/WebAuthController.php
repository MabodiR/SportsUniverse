<?php

namespace App\Http\Controllers\Web;

use App\Domain\Profiles\Actions\EnsureProfileSlug;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SocialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class WebAuthController extends Controller
{
    private const SOCIAL_PROVIDERS = ['google', 'apple', 'facebook', 'microsoft'];
    public function loginPage(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function forgotPasswordPage(): Response { return Inertia::render('Auth/ForgotPassword', ['status' => session('status')]); }
    public function sendPasswordResetLink(Request $request): RedirectResponse { $request->validate(['email'=>['required','email']]);$status=PasswordBroker::sendResetLink($request->only('email'));return $status===PasswordBroker::RESET_LINK_SENT?back()->with('status',__($status)):back()->withErrors(['email'=>__($status)]); }
    public function resetPasswordPage(Request $request,string $token): Response { return Inertia::render('Auth/ResetPassword',['token'=>$token,'email'=>$request->string('email')->value()]); }
    public function resetPassword(Request $request): RedirectResponse { $data=$request->validate(['token'=>['required'],'email'=>['required','email'],'password'=>['required','confirmed',Password::min(8)->letters()->numbers()]]);$status=PasswordBroker::reset($data,function(User $user,string $password):void{$user->forceFill(['password'=>$password,'remember_token'=>Str::random(60)])->save();event(new PasswordReset($user));});return $status===PasswordBroker::PASSWORD_RESET?redirect('/login')->with('status',__($status)):back()->withErrors(['email'=>__($status)]); }
    public function verificationPage(Request $request): Response { return Inertia::render('Auth/VerifyEmail',['verified'=>$request->user()->hasVerifiedEmail(),'status'=>session('success')]); }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['login' => ['required', 'string'], 'password' => ['required', 'string'], 'remember' => ['nullable', 'boolean']]);
        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        if (! Auth::attempt([$field => $data['login'], 'password' => $data['password'], 'status' => 'active'], (bool) ($data['remember'] ?? false))) {
            return back()->withErrors(['login' => 'The supplied credentials are invalid.'])->onlyInput('login');
        }
        $request->session()->regenerate();

        return redirect()->intended('/feed');
    }

    public function socialRedirect(Request $request, string $provider): SymfonyRedirectResponse
    {
        if (! in_array($provider, self::SOCIAL_PROVIDERS, true)) {
            abort(404);
        }

        if (blank(config("services.$provider.client_id")) || blank(config("services.$provider.client_secret"))) {
            return redirect('/login')->withErrors(['social' => ucfirst($provider).' login is not configured yet.']);
        }

        if ($request->boolean('mobile')) {
            $request->session()->put('social_mobile', ['device_name' => $request->string('device_name', 'mobile')->limit(120)->value()]);
        } else {
            $request->session()->forget('social_mobile');
        }

        return Socialite::driver($provider)->redirect();
    }

    public function socialCallback(Request $request, string $provider, EnsureProfileSlug $slugs): RedirectResponse
    {
        if (! in_array($provider, self::SOCIAL_PROVIDERS, true)) {
            abort(404);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable $exception) {
            report($exception);

            return redirect('/login')->withErrors(['social' => 'We could not sign you in with '.ucfirst($provider).'. Please try again.']);
        }

        $account = SocialAccount::where('provider', $provider)->where('provider_user_id', $socialUser->getId())->first();
        $user = $account?->user;

        if (! $user) {
            $email = $socialUser->getEmail();
            if (! $email) {
                return redirect('/login')->withErrors(['social' => ucfirst($provider).' did not share an email address. Please allow email access or use password login.']);
            }

            $user = User::where('email', $email)->first();
            if (! $user) {
                $user = DB::transaction(function () use ($socialUser, $email, $provider): User {
                    $user = User::create([
                        'name' => $socialUser->getName() ?: Str::before($email, '@'),
                        'email' => $email,
                        'email_verified_at' => now(),
                        'password' => Str::random(48),
                    ]);
                    $user->profile()->create(['completeness' => 20, 'country' => 'ZA']);
                    $user->fanProfile()->create(['interested_sports' => []]);
                    $user->assignRole('fan');
                    $user->socialAccounts()->create(['provider' => $provider, 'provider_user_id' => $socialUser->getId()]);

                    return $user;
                });
                $slugs->execute($user->load('profile'));
            } else {
                $user->socialAccounts()->create(['provider' => $provider, 'provider_user_id' => $socialUser->getId()]);
            }
        }

        if ($user->status !== 'active') {
            return redirect('/login')->withErrors(['social' => 'This account is not active.']);
        }

        if ($mobile = $request->session()->pull('social_mobile')) {
            $code = Str::random(64);
            Cache::put('mobile-social:'.$code, ['user_id' => $user->id, 'device_name' => $mobile['device_name'] ?? 'mobile'], now()->addSeconds(60));

            return redirect()->away('sportuniverse://auth/callback?code='.urlencode($code));
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended('/feed');
    }

    public function registerPage(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request, EnsureProfileSlug $slugs): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'role' => ['required', Rule::in(['athlete', 'fan', 'coach', 'referee', 'linesman', 'scout', 'agent', 'club', 'academy', 'business', 'sponsor'])],
            'interested_sports' => ['nullable', 'array', 'max:20'],
            'interested_sports.*' => ['string', 'max:100'],
            'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'club_name' => ['nullable', 'string', 'max:160'],
            'playing_level' => ['nullable', 'string', 'max:40'],
            'specialisation' => ['nullable', 'string', 'max:160'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'organisation_name' => ['nullable', 'string', 'max:160'],
            'services' => ['nullable', 'string', 'max:1000'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'province' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'locality' => ['nullable', 'string', 'max:120'],
        ]);
        $user = DB::transaction(function () use ($data) {
            $user = User::create(collect($data)->only(['name','email','phone','password'])->all());
            $user->profile()->create(['completeness' => 35, 'bio' => $data['bio'] ?? null, 'date_of_birth' => $data['date_of_birth'] ?? null, 'country' => 'ZA', 'province' => $data['province'] ?? null, 'city' => $data['city'] ?? null, 'locality' => $data['locality'] ?? null]);
            $user->assignRole($data['role']);
            if ($data['role'] === 'fan') $user->fanProfile()->create(['interested_sports' => $data['interested_sports'] ?? []]);
            if ($data['role'] === 'athlete') $user->athleteProfile()->create(['sport_id' => $data['sport_id'] ?? null, 'position_id' => $data['position_id'] ?? null, 'club_name' => $data['club_name'] ?? null, 'playing_level' => $data['playing_level'] ?? null]);
            if (in_array($data['role'], ['coach','referee','linesman','scout','agent'], true)) $user->professionalProfile()->create(['professional_type'=>$data['role'],'specialisation'=>$data['specialisation']??null,'years_experience'=>$data['years_experience']??null]);
            if (in_array($data['role'], ['club','academy','business','sponsor'], true)) $user->organisationProfile()->create(['organisation_name'=>($data['organisation_name']??null)?:$data['name'],'organisation_type'=>$data['role'],'contact_email'=>$data['email'],'contact_phone'=>$data['phone']??null,'services'=>array_values(array_filter(array_map('trim',explode(',', $data['services']??''))))]);

            return $user;
        });
        $slugs->execute($user->load('profile'));
        $user->sendEmailVerificationNotification();
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/feed')->with('success', 'Welcome to SportUniverse. Complete your profile when you are ready.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
