<?php

use App\Http\Controllers\Web\FeedController;
use App\Http\Controllers\Web\AthleteProfileController;
use App\Http\Controllers\Web\VideoStreamController;
use App\Http\Controllers\Web\MessagingContextController;
use App\Http\Controllers\Api\V1\Feed\EngagementController;
use App\Http\Controllers\Api\V1\Messaging\MessageRequestController;
use App\Http\Controllers\Api\V1\Moderation\ReportController;
use App\Http\Controllers\Web\ModulePageController;
use App\Http\Controllers\Web\WebAuthController;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Http\Controllers\Api\V1\Media\MediaController;

Route::middleware('guest')->group(function () {
    Route::get('/login', [WebAuthController::class, 'loginPage'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login']);
    Route::get('/register', [WebAuthController::class, 'registerPage'])->name('register');
    Route::post('/register', [WebAuthController::class, 'register']);
});

Route::get('/', fn () => redirect('/feed'));
Route::get('/feed', FeedController::class)->name('feed');
Route::get('/feed/location/{location}', [FeedController::class, 'location'])->name('feed.location');
Route::get('/feed/sport/{sport}', [FeedController::class, 'sport'])->name('feed.sport');
Route::get('/feed/position/{position}', [FeedController::class, 'position'])->name('feed.position');
Route::get('/@{slug}', AthleteProfileController::class)->name('athletes.show');
Route::get('/watch/{video}/stream', VideoStreamController::class)->name('videos.stream');
Route::get('/media/{media}/display', [MediaController::class, 'download'])->name('media.public');
Route::get('/sitemap.xml', function () {
    $urls = collect([
        ['loc' => url('/feed'), 'lastmod' => now()->toDateString(), 'frequency' => 'daily', 'priority' => '1.0'],
    ])->merge(User::query()->where('status', 'active')->whereHas('profile', fn ($profile) => $profile->where('is_public', true)->whereNotNull('slug'))->with('profile:id,user_id,slug,updated_at')->limit(5000)->get()->map(fn (User $user) => ['loc' => url('/@'.$user->profile->slug), 'lastmod' => $user->profile->updated_at?->toDateString(), 'frequency' => 'weekly', 'priority' => '0.8']));
    return response()->view('sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');

Route::get('/password/reset', ModulePageController::class)->defaults('module', 'password-reset')->name('password.request');
Route::get('/auth/phone', ModulePageController::class)->defaults('module', 'phone-auth')->name('phone-auth');
Route::get('/auth/social', ModulePageController::class)->defaults('module', 'social-auth')->name('social-auth');
Route::get('/verify-account', ModulePageController::class)->defaults('module', 'verification')->name('verification.notice');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
    Route::post('/athletes/{user}/follow', [EngagementController::class, 'follow'])->name('web.athletes.follow');
    Route::delete('/athletes/{user}/follow', [EngagementController::class, 'unfollow'])->name('web.athletes.unfollow');
    Route::post('/posts/{video}/like', [EngagementController::class, 'like'])->name('web.posts.like');
    Route::post('/posts/{video}/save', [EngagementController::class, 'save'])->name('web.posts.save');
    Route::post('/posts/{video}/share', [EngagementController::class, 'share'])->name('web.posts.share');
    Route::post('/posts/{video}/comments', [EngagementController::class, 'comment'])->name('web.posts.comment');
    Route::post('/comments/{comment}/like', [EngagementController::class, 'likeComment'])->name('web.comments.like');
    Route::post('/athlete-message-requests', [MessageRequestController::class, 'store'])->name('web.message-requests.store');
    Route::get('/athletes/{user}/messaging-context', MessagingContextController::class)->name('web.messaging.context');
    Route::post('/conversations/{conversation}/messages', [\App\Http\Controllers\Api\V1\Messaging\MessageController::class, 'store'])->name('web.conversations.messages.store');
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
});
Route::get('/posts/{video}/comments', [\App\Http\Controllers\Api\V1\Feed\VideoController::class, 'comments'])->name('web.posts.comments');

Route::fallback(fn () => auth()->check() ? redirect('/feed') : redirect('/login'));
