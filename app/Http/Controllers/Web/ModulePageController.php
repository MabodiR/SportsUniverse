<?php

namespace App\Http\Controllers\Web;

use App\Domain\Feed\Models\Video;
use App\Domain\Opportunities\Models\Opportunity;
use App\Domain\Opportunities\Models\OpportunityApplication;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModulePageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $key = (string) $request->route('module');
        $pages = config('sportuniverse_pages');

        abort_unless(isset($pages[$key]), 404);

        if (in_array($key, ['messages', 'message-requests'], true)) {
            return Inertia::render('Messages/Index', [
                'initialTab' => $key === 'message-requests' ? 'requests' : 'messages',
            ]);
        }

        if ($key === 'upload') {
            return Inertia::render('Upload/Index');
        }

        if ($key === 'dashboard') {
            $user = $request->user()->load(['roles', 'profile', 'athleteProfile.sport', 'careerEntries.sport', 'careerEntries.position']);
            $isAthlete = $user->hasRole('athlete');
            $recentPosts = $user->videos()->latest()->limit(3)->get(['id', 'public_id', 'caption', 'status', 'created_at']);
            $recentCareer = $user->careerEntries()->with(['sport:id,name', 'position:id,name'])->latest()->limit(3)->get();
            $applications = OpportunityApplication::query()->where('user_id', $user->id)->count();
            $openOpportunities = Opportunity::query()->where('status', 'published')->where(fn ($query) => $query->whereNull('deadline')->orWhere('deadline', '>=', now()))->count();

            return Inertia::render('Dashboard/Index', ['dashboard' => [
                'user' => ['name' => $user->name, 'role' => $user->roles->first()?->name ?? 'member', 'sport' => $user->athleteProfile?->sport?->name],
                'metrics' => [
                    'profile_completion' => (int) ($user->profile?->completeness ?? 0),
                    'profile_views' => (int) ($user->profile?->views_count ?? 0),
                    'followers' => $user->followers()->count(),
                    'posts' => $user->videos()->count(),
                    'applications' => $applications,
                    'unread_notifications' => $user->unreadNotifications()->count(),
                ],
                'open_opportunities' => $openOpportunities,
                'activities' => $recentPosts->map(fn ($post) => ['type' => 'post', 'title' => $post->caption ?: 'SportUniverse post', 'meta' => ucfirst($post->status).' · '.$post->created_at->diffForHumans(), 'href' => '/feed'])->concat($recentCareer->map(fn ($entry) => ['type' => 'career', 'title' => $entry->team_name, 'meta' => collect([$entry->sport?->name, $entry->position?->name ?? $entry->role, $entry->level])->filter()->join(' · '), 'href' => '/profile']))->take(6)->values(),
                'actions' => $isAthlete ? [
                    ['title' => 'Complete your profile', 'description' => 'Improve how scouts and clubs discover you.', 'href' => '/profile/edit', 'kind' => 'profile'],
                    ['title' => 'Add career history', 'description' => 'Keep your teams, level and playing position current.', 'href' => '/profile', 'kind' => 'career'],
                    ['title' => 'Upload a highlight', 'description' => 'Show your latest performance to the community.', 'href' => '/upload', 'kind' => 'upload'],
                    ['title' => 'Find opportunities', 'description' => $openOpportunities.' open opportunities available.', 'href' => '/opportunities', 'kind' => 'opportunity'],
                ] : [
                    ['title' => 'Complete your profile', 'description' => 'Help the community understand who you are.', 'href' => '/profile/edit', 'kind' => 'profile'],
                    ['title' => 'Discover talent', 'description' => 'Search athletes across sports and locations.', 'href' => '/explore', 'kind' => 'career'],
                    ['title' => 'Review opportunities', 'description' => $openOpportunities.' open opportunities available.', 'href' => '/opportunities', 'kind' => 'opportunity'],
                ],
            ]]);
        }

        if ($key === 'sponsorship') {
            return Inertia::render('Promote/Index');
        }

        if ($key === 'profile') {
            return Inertia::render('Profile/Index');
        }

        if ($key === 'profile-edit') {
            return Inertia::render('Profile/Edit');
        }

        if (in_array($key, ['statistics', 'achievements'], true)) {
            abort_unless($request->user()->hasRole('athlete'), 403);
            return Inertia::render('Profile/Career', ['initialTab' => $key]);
        }

        if ($key === 'explore') {
            return Inertia::render('Explore/Index');
        }
        if ($key === 'women-in-sports') {
            return Inertia::render('Women/Index');
        }

        if ($key === 'club-tools') {
            return Inertia::render('Club/Workspace');
        }

        if ($key === 'live') {
            return Inertia::render('Live/Index', ['initialStream' => $request->route('stream')]);
        }

        if ($key === 'analytics') {
            return Inertia::render('Analytics/Index');
        }

        if ($key === 'devices') {
            return Inertia::render('Settings/Devices');
        }

        if ($key === 'notifications') {
            return Inertia::render('Notifications/Index');
        }

        if ($key === 'opportunities') {
            return Inertia::render('Opportunities/Index');
        }
        if (in_array($key, ['applications', 'application-tracking'], true)) {
            return Inertia::render('Applications/Index');
        }
        if (in_array($key, ['gallery', 'upload-status'], true)) {
            return Inertia::render('Media/Library');
        }
        if ($key === 'opportunity-create') {
            return Inertia::render('Opportunities/Create');
        }
        if ($key === 'saved') {
            return Inertia::render('Saved/Index', ['videos' => $request->user()->belongsToMany(Video::class, 'saved_videos')->get()]);
        }

        if ($key === 'saved') {
            return app(FeedController::class)->saved($request);
        }

        return Inertia::render('Workspace/ModulePage', [
            'page' => array_merge(['key' => $key], $pages[$key]),
        ]);
    }
}
