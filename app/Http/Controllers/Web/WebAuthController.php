<?php

namespace App\Http\Controllers\Web;

use App\Domain\Profiles\Actions\EnsureProfileSlug;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class WebAuthController extends Controller
{
    public function loginPage(): Response
    {
        return Inertia::render('Auth/Login');
    }

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
            'role' => ['required', Rule::in(['athlete', 'fan', 'coach', 'scout', 'agent', 'club', 'academy', 'business', 'sponsor'])],
            'interested_sports' => ['nullable', 'array', 'max:20'],
            'interested_sports.*' => ['string', 'max:100'],
            'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'club_name' => ['nullable', 'string', 'max:160'],
            'playing_level' => ['nullable', 'string', 'max:40'],
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

            return $user;
        });
        $slugs->execute($user->load('profile'));
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
