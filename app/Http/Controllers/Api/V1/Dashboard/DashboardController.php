<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Domain\Opportunities\Models\Opportunity;
use App\Domain\Opportunities\Models\OpportunityApplication;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles', 'profile', 'athleteProfile.sport']);
        $recentPosts = $user->videos()->latest()->limit(4)->get(['public_id', 'caption', 'status', 'created_at']);
        $recentCareer = $user->careerEntries()->with(['sport:id,name', 'position:id,name'])->latest()->limit(4)->get();
        $openOpportunities = Opportunity::query()->where('status', 'published')->where(fn ($query) => $query->whereNull('deadline')->orWhere('deadline', '>=', now()))->count();
        $applications = OpportunityApplication::query()->where('user_id', $user->id)->count();
        $isAthlete = $user->hasRole('athlete');

        $actions = $isAthlete ? [
            ['title' => 'Complete your profile', 'description' => 'Improve how scouts and clubs discover you.', 'route' => '/profile/edit', 'icon' => 'person'],
            ['title' => 'Update career portfolio', 'description' => 'Keep your teams, achievements and statistics current.', 'route' => '/profile/career', 'icon' => 'trophy'],
            ['title' => 'Upload a highlight', 'description' => 'Show your latest performance to the community.', 'route' => '/upload', 'icon' => 'cloud-upload'],
            ['title' => 'Find opportunities', 'description' => "$openOpportunities open opportunities available.", 'route' => '/(tabs)/opportunities', 'icon' => 'briefcase'],
        ] : [
            ['title' => 'Complete your profile', 'description' => 'Help the community understand who you are.', 'route' => '/profile/edit', 'icon' => 'person'],
            ['title' => 'Discover talent', 'description' => 'Search athletes across sports and locations.', 'route' => '/(tabs)/explore', 'icon' => 'search'],
            ['title' => 'Review opportunities', 'description' => "$openOpportunities open opportunities available.", 'route' => '/(tabs)/opportunities', 'icon' => 'briefcase'],
        ];

        $activities = $recentPosts->map(fn ($post) => [
            'type' => 'post',
            'title' => $post->caption ?: 'SportsUniverse post',
            'meta' => ucfirst($post->status).' · '.$post->created_at->diffForHumans(),
            'route' => '/profile/my-posts',
        ])->concat($recentCareer->map(fn ($entry) => [
            'type' => 'career',
            'title' => $entry->team_name,
            'meta' => collect([$entry->sport?->name, $entry->position?->name ?? $entry->role, $entry->level])->filter()->join(' · '),
            'route' => '/profile/career',
        ]))->take(7)->values();

        return response()->json(['data' => [
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
            'actions' => $actions,
            'activities' => $activities,
        ]]);
    }
}
