<?php

use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Feed\EngagementController;
use App\Http\Controllers\Api\V1\Feed\FeedPreferenceController;
use App\Http\Controllers\Api\V1\Feed\VideoController;
use App\Http\Controllers\Api\V1\Media\MediaController;
use App\Http\Controllers\Api\V1\Messaging\MessageController;
use App\Http\Controllers\Api\V1\Messaging\MessageRequestController;
use App\Http\Controllers\Api\V1\Moderation\ReportController;
use App\Http\Controllers\Web\AthleteProfileController;
use App\Http\Controllers\Web\ClubPageController;
use App\Http\Controllers\Web\MobileAssociationController;
use App\Http\Controllers\Web\FeedController;
use App\Http\Controllers\Web\MessagingContextController;
use App\Http\Controllers\Web\ModulePageController;
use App\Http\Controllers\Web\VideoStreamController;
use App\Http\Controllers\Web\WebAuthController;
use App\Domain\Opportunities\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [WebAuthController::class, 'loginPage'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login']);
    Route::get('/register', [WebAuthController::class, 'registerPage'])->name('register');
    Route::post('/register', [WebAuthController::class, 'register']);
    Route::get('/password/reset', [WebAuthController::class, 'forgotPasswordPage'])->name('password.request');
    Route::post('/password/email', [WebAuthController::class, 'sendPasswordResetLink'])->name('password.email');
    Route::get('/password/reset/{token}', [WebAuthController::class, 'resetPasswordPage'])->name('password.reset');
    Route::post('/password/reset', [WebAuthController::class, 'resetPassword'])->name('password.update');
    Route::get('/auth/{provider}/redirect', [WebAuthController::class, 'socialRedirect'])->name('social.redirect');
    Route::get('/auth/{provider}/callback', [WebAuthController::class, 'socialCallback'])->name('social.callback');
});

Route::get('/', fn () => redirect('/feed'));
Route::get('/about', fn () => Inertia::render('Public/About'))->name('about');
Route::get('/privacy-policy', fn () => Inertia::render('Public/PrivacyPolicy'))->name('privacy-policy');
Route::get('/mobile-app', fn () => Inertia::render('MobileApp/Download', [
    'downloads' => [
        'ios' => config('services.mobile_app.ios_url'),
        'android' => config('services.mobile_app.android_url'),
        'direct' => config('services.mobile_app.direct_url'),
    ],
]))->name('mobile.download');
Route::get('/feed', FeedController::class)->name('feed');
Route::get('/feed/location/{location}', [FeedController::class, 'location'])->name('feed.location');
Route::get('/feed/sport/{sport}', [FeedController::class, 'sport'])->name('feed.sport');
Route::get('/feed/position/{position}', [FeedController::class, 'position'])->name('feed.position');
Route::get('/@{slug}', AthleteProfileController::class)->name('athletes.show');
Route::get('/clubs/{slug}', ClubPageController::class)->name('clubs.show');
Route::get('/.well-known/apple-app-site-association', [MobileAssociationController::class, 'apple'])->name('mobile.association.apple');
Route::get('/.well-known/assetlinks.json', [MobileAssociationController::class, 'android'])->name('mobile.association.android');
Route::get('/watch/{video}/stream', VideoStreamController::class)->name('videos.stream');
Route::get('/media/{media}/display', [MediaController::class, 'download'])->name('media.public');
Route::get('/sitemap.xml', function () {
    $urls = collect([
        ['loc' => url('/feed'), 'lastmod' => now()->toDateString(), 'frequency' => 'daily', 'priority' => '1.0'],
    ])->merge(User::query()->where('status', 'active')->whereHas('profile', fn ($profile) => $profile->where('is_public', true)->whereNotNull('slug'))->with('profile:id,user_id,slug,updated_at')->limit(5000)->get()->map(fn (User $user) => ['loc' => url('/@'.$user->profile->slug), 'lastmod' => $user->profile->updated_at?->toDateString(), 'frequency' => 'weekly', 'priority' => '0.8']));

    return response()->view('sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');


Route::middleware('auth')->group(function () {
    Route::get('/verify-account', [WebAuthController::class, 'verificationPage'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {$request->fulfill();return redirect('/feed')->with('success', 'Your email address has been verified.');})->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', function (Request $request) {if (! $request->user()->hasVerifiedEmail()) $request->user()->sendEmailVerificationNotification();return back()->with('success', 'A new verification link has been sent.');})->middleware('throttle:6,1')->name('verification.send');
    Route::get('/api/v1/auth/sessions', [SessionController::class, 'index']);
    Route::delete('/api/v1/auth/sessions/others', [SessionController::class, 'destroyOthers']);
    Route::delete('/api/v1/auth/sessions/{session}', [SessionController::class, 'destroy']);
    Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
    Route::post('/athletes/{user}/follow', [EngagementController::class, 'follow'])->name('web.athletes.follow');
    Route::delete('/athletes/{user}/follow', [EngagementController::class, 'unfollow'])->name('web.athletes.unfollow');
    Route::post('/posts/{video}/like', [EngagementController::class, 'like'])->name('web.posts.like');
    Route::post('/posts/{video}/save', [EngagementController::class, 'save'])->name('web.posts.save');
    Route::post('/posts/{video}/share', [EngagementController::class, 'share'])->name('web.posts.share');
    Route::post('/posts/{video}/not-interested', [FeedPreferenceController::class, 'store'])->name('web.posts.not-interested');
    Route::post('/posts/{video}/comments', [EngagementController::class, 'comment'])->name('web.posts.comment');
    Route::post('/comments/{comment}/like', [EngagementController::class, 'likeComment'])->name('web.comments.like');
    Route::post('/athlete-message-requests', [MessageRequestController::class, 'store'])->name('web.message-requests.store');
    Route::get('/athletes/{user}/messaging-context', MessagingContextController::class)->name('web.messaging.context');
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])->name('web.conversations.messages.store');
    Route::post('/post-reports', [ReportController::class, 'store'])->name('web.reports.store');
    Route::get('/following', [FeedController::class, 'following'])->name('following');

    $pages = [
        '/settings/devices' => 'devices',
        '/dashboard' => 'dashboard',
        '/sponsorship' => 'sponsorship',
        '/analytics' => 'analytics',
        '/onboarding' => 'onboarding',
        '/onboarding/athlete' => 'athlete-onboarding',
        '/onboarding/fan' => 'fan-onboarding',
        '/onboarding/completeness' => 'completeness',
        '/profile' => 'profile',
        '/profile/edit' => 'profile-edit',
        '/profile/statistics' => 'statistics',
        '/profile/achievements' => 'achievements',
        '/profile/gallery' => 'gallery',
        '/profile/highlights' => 'highlights',
        '/upload' => 'upload',
        '/uploads/status' => 'upload-status',
        '/videos/watch' => 'video',
        '/explore' => 'explore',
        '/women-in-sports' => 'women-in-sports',
        '/club-tools' => 'club-tools',
        '/live' => 'live',
        '/comments' => 'comments',
        '/saved' => 'saved',
        '/messages' => 'messages',
        '/message-requests' => 'message-requests',
        '/notifications' => 'notifications',
        '/opportunities' => 'opportunities',
        '/opportunities/create' => 'opportunity-create',
        '/opportunities/featured' => 'opportunity-detail',
        '/applications' => 'applications',
        '/applications/tracking' => 'application-tracking',
        '/management/comments' => 'moderation',
        '/management/campaigns' => 'campaigns',
        '/management/taxonomy' => 'taxonomy',
        '/management/settings' => 'system-settings',
        '/management/featured-athletes' => 'featured-athletes',
        '/management/reports' => 'reports',
    ];

    foreach ($pages as $uri => $module) {
        Route::get($uri, ModulePageController::class)->defaults('module', $module)->name("workspace.$module");
    }
    Route::get('/opportunities/{opportunity}', fn (Opportunity $opportunity) => Inertia::render('Opportunities/Show', ['opportunityId' => $opportunity->public_id]))->name('opportunities.show');
    Route::get('/live/{stream}', ModulePageController::class)->defaults('module', 'live')->name('live.show');
});
Route::get('/posts/{video}/comments', [VideoController::class, 'comments'])->name('web.posts.comments');

Route::fallback(fn () => auth()->check() ? redirect('/feed') : redirect('/login'));
